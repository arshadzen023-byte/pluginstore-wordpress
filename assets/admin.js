jQuery(function($){
  const state={config:ARKDesigner.config||{},page:'home',selected:null,view:'page',dirty:false};
  const liveCache={categories:null,products:null,targets:null,sliders:{},sliderPending:{},loading:false};
  const $pages=$('#ark-pages'),$components=$('#ark-components'),$preview=$('#ark-preview'),$inspector=$('#ark-inspector-content');
  const labels={home:'Home page',categories:'Categories page',wishlist:'Wishlist page',product:'Product page',account:'Account page'};
  const collapseState={left:false,right:false};
  const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const page=()=>state.config.pages[state.page]||(state.config.pages[state.page]={title:labels[state.page],sections:[]});
  const sections=()=>page().sections||(page().sections=[]);
  function toast(msg,error){
    $('.ark-toast').remove();
    let text=String(msg??'');
    if(text.length>150)text=text.slice(0,147)+'…';
    $('<div class="ark-toast"></div>').toggleClass('error',!!error).text(text).appendTo('body');
    setTimeout(()=>$('.ark-toast').fadeOut(250,()=>$('.ark-toast').remove()),2600);
  }
  function markDirty(){state.dirty=true;$('#ark-version-label').text('Unsaved changes');}
  function renderPages(){
    $pages.empty();
    Object.keys(labels).forEach(key=>$('<button class="ark-page-btn"></button>').toggleClass('active',state.page===key).text('▣  '+labels[key]).attr('data-page',key).appendTo($pages));
  }
  function renderComponents(){
    $components.empty();
    const search=$('<input type="search" id="ark-component-search" class="ark-component-search" placeholder="⌕  Search components…">');
    $components.append(search);

    Object.entries(ARKDesigner.components||{}).forEach(([type,item])=>{
      const btn=$('<button class="ark-component-btn"></button>').attr('data-type',type);
      $('<span class="ark-component-icon"></span>').text(item.icon||'＋').appendTo(btn);
      $('<span></span>').append($('<b></b>').text(item.label)).append($('<small></small>').text(item.description||'')).appendTo(btn);
      $components.append(btn);
    });
    search.on('input',function(){const q=$(this).val().toLowerCase();$components.find('.ark-component-btn').each(function(){$(this).toggle($(this).text().toLowerCase().includes(q));});});
  }
  function selected(){return sections().find(x=>x.id===state.selected)||null;}
  function previewSection(s,index){
    const title=s.title||ARKDesigner.components[s.type]?.label||s.type;
    const card=$('<div class="ark-preview-card"></div>').toggleClass('selected',s.id===state.selected).attr('data-id',s.id);
    $('<div class="ark-preview-label"></div>').text(title).appendTo(card);
    const pct=Math.max(10,Math.min(100,Number(s.width_pct)||100));
    card.css({width:pct+'%',minHeight:Number(s.min_height_px)>0?Number(s.min_height_px)+'px':'',maxWidth:Number(s.max_width_px)>0?Number(s.max_width_px)+'px':'none',height:Number(s.height_px)>0?Number(s.height_px)+'px':'auto',padding:[s.padding_top??15,s.padding_right??15,s.padding_bottom??15,s.padding_left??15].map(v=>Number(v)+'px').join(' '),margin:[s.margin_top||0,s.margin_right||0,s.margin_bottom||0,s.margin_left||0].map(v=>Number(v)+'px').join(' '),marginLeft:s.alignment==='right'?'auto':s.alignment==='center'?'auto':'0',marginRight:s.alignment==='left'?'auto':s.alignment==='center'?'auto':'0',borderRadius:(Number(s.corner_radius)||16)+'px',background:s.section_background||''});
    if(s.subtitle||s.text)$('<div class="ark-preview-sub"></div>').text(s.subtitle||s.text).appendTo(card);
    if(s.type==='search'){
      $('<div class="ark-real-search"></div>').text('⌕  '+(s.hints?.[0]||s.placeholder||'Search products…')).appendTo(card);
    }else if(s.type==='ark_live_slider'){
      const saved=liveCache.sliders[s.slider_key];
      if(!saved)loadLiveSlider(s);
      const slides=saved?.slides||[];
      const rail=$('<div class="ark-preview-slider-rail ark-live-slider-preview"></div>')
        .css({height:(Number(saved?.settings?.height_px)||178)+'px',gap:'8px'});
      if(slides.length){
        slides.slice(0,4).forEach(slide=>{
          const frame=$('<div class="ark-preview-real-banner"></div>').css({
            height:'100%',
            flex:'0 0 '+(Math.max(.45,Math.min(1,Number(saved.settings.card_width)||1))*100)+'%',
            borderRadius:(Number(saved.settings.radius)||16)+'px'
          });
          $('<img alt="">').attr('src',slide.image_url).css({
            width:'100%',height:'100%',objectFit:saved.settings.image_fit||'cover'
          }).appendTo(frame);
          if(slide.title)$('<span class="ark-preview-slide-title"></span>').text(slide.title).appendTo(frame);
          rail.append(frame);
        });
      }else{
        $('<div class="ark-live-slider-empty"></div>').text('Add images in SQL-backed Slider Properties').appendTo(rail);
      }
      card.append(rail);
      $('<small class="ark-live-slider-status"></small>')
        .text(slides.length+' saved slides · '+(saved?.settings?.transition||'slide')+' transition')
        .appendTo(card);
    }else if(s.type==='image' && s.image_url){
      $('<img class="ark-preview-single-image" alt="">').attr('src',s.image_url).appendTo(card);
    }else if(['product_grid','product_carousel','recently_viewed','category_grid','icon_grid'].includes(s.type)){
      const grid=$('<div class="ark-preview-grid"></div>').attr('data-card-design',s.card_design||'flipkart');
      const liveItems=s.type==='category_grid'?(liveCache.categories||[]):(liveCache.products||[]);
      if(liveItems.length){
        liveItems.slice(0,Math.min(4,Number(s.limit||4))).forEach(item=>{
          const tile=$('<div class="ark-preview-product ark-real-tile"></div>');
          const img=item.image||item.images?.[0]?.src||'';
          if(img)$('<img alt="">').attr('src',img).appendTo(tile);
          $('<b></b>').text(item.name||item.title||'').appendTo(tile);
          if(item.price_html||item.prices?.price)$('<small></small>').text(item.price_html?String(item.price_html).replace(/<[^>]*>/g,''):item.prices.price).appendTo(tile);
          grid.append(tile);
        });
      }else{
        const count=Math.max(2,Math.min(4,Number(s.limit||4)));
        for(let i=0;i<Math.min(count,4);i++)$('<div class="ark-preview-product"></div>').text(s.type==='category_grid'?'Loading live category…':'Loading live product…').appendTo(grid);
      }
      card.append(grid);
    }
    else if(s.type==='address_selector'){
      const address=$('<div class="ark-preview-address"></div>');
      $('<b></b>').text('⌖  '+(s.title||'Delivery address')).appendTo(address);
      $('<div></div>').text(s.subtitle||'Choose where your order should arrive.').appendTo(address);
      $('<small></small>').text('Saved address'+(s.show_phone===false?'':' • Phone')+(s.show_city_pin===false?'':' • City / PIN')+(s.show_edit===false?'':' • Edit')).appendTo(address);
      if(s.allow_address_switch!==false)$('<small></small>').text('Switch saved address').appendTo(address);
      card.append(address);
    }else if(s.type==='account_menu'){
      const items=Array.isArray(s.items)?s.items:[];
      const list=$('<div class="ark-preview-menu"></div>');
      items.slice(0,6).forEach(item=>{
        const row=$('<div class="ark-preview-menu-row"></div>');
        $('<b></b>').text(item.title||'Menu item').appendTo(row);
        $('<small></small>').text(item.subtitle||item.action||'Action').appendTo(row);
        list.append(row);
      });
      if(!items.length)list.text('Add account list items in Properties.');
      card.append(list);
    }else if(s.type==='product_gallery'){
      const gallery=$('<div class="ark-preview-gallery"></div>').css({height:(Number(s.gallery_height)||390)+'px',position:'relative',overflow:'hidden',borderRadius:'12px',background:'#f2f4f8',display:'grid',placeItems:'center'});
      $('<div></div>').text('Swipe product images · Tap to zoom').css({fontSize:'11px',fontWeight:700,color:'#64728a'}).appendTo(gallery);
      const actions=$('<div class="ark-preview-gallery-actions"></div>').css({position:'absolute',top:(Number(s.action_offset_top)||14)+'px',right:(Number(s.action_offset_right)||14)+'px',display:'flex',flexDirection:s.action_layout==='horizontal'?'row':'column',gap:(Number(s.action_gap)||9)+'px'});
      if(s.show_wishlist!==false)$('<span>♥</span>').appendTo(actions);
      if(s.show_share!==false)$('<span>↗</span>').appendTo(actions);
      actions.children().css({width:(Number(s.action_button_size)||44)+'px',height:(Number(s.action_button_size)||44)+'px',display:'grid',placeItems:'center',borderRadius:'50%',background:s.action_background||'#fff',color:s.action_icon_color||'#263248',fontSize:(Number(s.action_icon_size)||21)+'px',boxShadow:'0 2px 8px #0002'});
      gallery.append(actions);
      if(s.show_dots!==false)$('<div>● ○ ○</div>').css({position:'absolute',bottom:'10px',left:0,right:0,textAlign:'center',fontSize:'11px',color:'#4361ee'}).appendTo(gallery);
      card.append(gallery);
    }else if(s.type==='product_recommended'){
      const promo=$('<div class="ark-preview-promo"></div>').css({height:(Number(s.promo_card_height)||112)+'px',display:'flex',alignItems:'center',gap:'9px',padding:'8px',marginTop:'8px',border:'1px solid #e7ebf3',borderRadius:'12px',background:'#fff',position:'relative'});
      $('<div>Product</div>').css({width:(Number(s.promo_image_size)||72)+'px',height:(Number(s.promo_image_size)||72)+'px',display:'grid',placeItems:'center',background:'#f1f3f8',borderRadius:'8px',fontSize:'10px'}).appendTo(promo);
      $('<div><b>Recommended product</b><small style="display:block;color:#16834d;margin-top:5px">Offer price · Discount</small></div>').appendTo(promo);
      if(s.show_ad_badge!==false)$('<span>◉ '+(s.ad_label||'AD')+'</span>').css({position:'absolute',right:'7px',top:'6px',fontSize:'8px',fontWeight:900,color:'#52627c',background:'#eaf0fa',borderRadius:'5px',padding:'3px 5px'}).appendTo(promo);
      card.append(promo);
    }else if(['product_title','product_price','product_delivery','product_description','product_specs','product_reviews','product_offers'].includes(s.type)){
      $('<div class="ark-preview-address"></div>').text(s.type.replace('product_','').replace(/_/g,' ').toUpperCase()+' • Native product data').appendTo(card);
    }
        const actions=$('<div class="ark-inspector-actions"></div>');
    $('<button type="button">↑</button>').attr('data-move','up').attr('data-id',s.id).appendTo(actions);
    $('<button type="button">↓</button>').attr('data-move','down').attr('data-id',s.id).appendTo(actions);
    $('<button type="button" class="danger">Remove</button>').attr('data-delete',s.id).appendTo(actions);
    card.append(actions);
    card.on('click',function(e){if($(e.target).closest('button').length)return;state.selected=s.id;state.view='page';render();});
    return card;
  }
  function renderPreview(){
    $preview.empty();
    if(state.view==='splash'){
      const sp=state.config.splash||{};
      $preview.css('background',sp.background||'#f5f7fb');
      const splash=$('<div class="ark-splash-preview"></div>').css({height:'100%',minHeight:'600px',position:'relative',overflow:'hidden',display:'grid',placeItems:'center',background:sp.background||'#f5f7fb',padding:0});
      const splashImage=sp.image_url||'';
      if(splashImage){
        $('<img alt="Splash preview">').attr('src',splashImage).css({
          width:'100%',height:'100%',minHeight:'600px',objectFit:sp.fit==='cover'?'cover':(sp.fit==='fill'?'fill':'contain'),
          position:'absolute',inset:0
        }).appendTo(splash);
      } else {
        $('<div style="width:100%;height:600px;display:grid;place-items:center;color:#6576c6"></div>')
          .text('Select a full-screen splash image').appendTo(splash);
      }
      $preview.append(splash);return;
    }
    const theme=state.config.theme||{};
    $preview.css('background',theme.background||'#f5f7fb');
    const app=state.config.app||{};
    const appHeader=$('<div class="ark-preview-app-header"></div>').css({background:theme.header_background||'#fff',color:theme.header_text||'#202733'});
    if(app.icon_url)$('<img alt="">').attr('src',app.icon_url).css({width:'30px',height:'30px',objectFit:'cover',borderRadius:'8px',marginRight:'9px'}).appendTo(appHeader);
    $('<span></span>').text(app.name||'GlowHit').appendTo(appHeader);
    $preview.append(appHeader);
    sections().forEach((s,i)=>{if(s.visible!==false)$preview.append(previewSection(s,i));});
    $('<div class="ark-preview-add">＋ Add a component from the left panel</div>').appendTo($preview);
    const nav=state.config.navigation||{};
    const navItems=Array.isArray(nav.items)?nav.items:[];
    const phoneNav=$('.ark-phone-nav').empty().toggle(nav.visible!==false);
    phoneNav.css({background:theme.navigation_background||'#f0eff5',color:theme.header_text||'#202733'});
    navItems.forEach((item,index)=>{
      const cell=$('<div class="ark-phone-nav-item"></div>');
      $('<span class="ark-phone-nav-icon"></span>').text(item.icon||'home').appendTo(cell);
      $('<small></small>').text(item.label||'Menu').appendTo(cell);
      if(index===0)cell.addClass('active').css('background',theme.navigation_indicator||'#dde4ff');
      phoneNav.append(cell);
    });
    if($preview.data('ui-sortable'))$preview.sortable('destroy');
    if($.fn.sortable){
      $preview.sortable({
        items:'.ark-preview-card',
        handle:'.ark-preview-label',
        placeholder:'ark-preview-card',
        update:function(){
          const ordered=[];
          $preview.children('.ark-preview-card').each(function(){
            const id=$(this).attr('data-id');
            const item=sections().find(s=>s.id===id);
            if(item)ordered.push(item);
          });
          if(ordered.length===sections().length){page().sections=ordered;markDirty();}
        }
      });
    }
  }
  function field(label,key,value,type='text',hint=''){
    const wrap=$('<div class="ark-field"></div>');
    $('<label></label>').text(label).appendTo(wrap);
    let input;
    if(type==='textarea')input=$('<textarea></textarea>');
    else if(type==='color')input=$('<input type="color">');
    else if(type==='checkbox')input=$('<input type="checkbox">').prop('checked',!!value);
    else if(type==='select')input=$('<select></select>');
    else input=$('<input>').attr('type',type);
    if(type==='select'&&key==='fit'){
      [['contain','Contain'],['cover','Cover'],['fill','Fill']].forEach(([v,l])=>input.append($('<option></option>').val(v).text(l)));
    } else if(type==='select'&&key==='animation'){
      [['none','None'],['fade','Fade'],['zoom','Zoom'],['slide','Slide up']].forEach(([v,l])=>input.append($('<option></option>').val(v).text(l)));
    }
    if(type!=='checkbox')input.val(value??'');
    input.attr('data-field',key).on('input change',function(){
      let target=selected();
      if(state.view==='theme') target=state.config.theme||(state.config.theme={});
      if(state.view==='celebration') target=state.config.celebration||(state.config.celebration={enabled:false,duration_ms:4200,pages:['home']});
      if(state.view==='splash' && key.startsWith('loading_')){target=state.config.loading||(state.config.loading={enabled:true,animation_url:'https://pluginstore.shop/wp-content/themes/ark-material-commerce/assets/animations/loading.json',duration_ms:900,pages:['home','categories','product','account']});key=key.substring(8);}
      else if(state.view==='splash') target=state.config.splash||(state.config.splash={});
      if(state.view==='navigation') target=state.config.navigation||(state.config.navigation={});
      if(state.view==='app') target=state.config.app||(state.config.app={});
      if(!target)return;
      const fieldValue=type==='checkbox'?$(this).is(':checked'):(type==='number'?Number($(this).val()):$(this).val());
      target[key]=key==='pages' && (target===state.config.loading||target===state.config.celebration)?String(fieldValue).split(',').map(v=>v.trim()).filter(Boolean):fieldValue;
      markDirty();renderPreview();
    });
    wrap.append(input);
    if(type==='url'&&['image_url','logo_url','icon_url','splash_image_url'].includes(key)){
      const media=$('<button type="button" class="ark-media-pick">Choose from Media Library</button>');
      media.on('click',function(e){
        e.preventDefault();
        const frame=wp.media({title:'Select image',button:{text:'Use image'},multiple:false});
        frame.on('select',function(){
          const item=frame.state().get('selection').first().toJSON();
          input.val(item.url).trigger('change');
        });
        frame.open();
      });
      wrap.append(media);
    }
    if(hint)$('<div class="description"></div>').text(hint).appendTo(wrap);
    return wrap;
  }
  function choiceField(label,key,value,options,hint=''){
    const wrap=$('<div class="ark-field"></div>');
    $('<label></label>').text(label).appendTo(wrap);
    const select=$('<select></select>').attr('data-field',key);
    options.forEach(([v,l])=>select.append($('<option></option>').val(v).text(l)));
    select.val(value??options[0]?.[0]??'').on('change',function(){
      let target=selected();
      if(state.view==='app') target=state.config.app||(state.config.app={});
      if(state.view==='theme') target=state.config.theme||(state.config.theme={});
      if(state.view==='splash') target=state.config.splash||(state.config.splash={});
      if(!target)return;
      target[key]=$(this).val();markDirty();renderPreview();
    });
    wrap.append(select);
    if(hint)$('<div class="description"></div>').text(hint).appendTo(wrap);
    return wrap;
  }

  function inAppTargetField(label,value,onChange,hint=''){
    const wrap=$('<div class="ark-field"></div>');
    $('<label></label>').text(label).appendTo(wrap);
    const select=$('<select class="ark-inapp-target"></select>');
    const current=String(value||'');
    const fill=data=>{
      select.empty().append($('<option></option>').val('').text('Select app page or product…'));
      (data?.pages||[]).forEach(item=>{
        select.append($('<option></option>').val(item.value).text(item.label));
      });
      const products=data?.products||[];
      if(products.length){
        const group=$('<optgroup label="WooCommerce products"></optgroup>');
        products.forEach(item=>group.append($('<option></option>').val('product:'+item.id).text('#'+item.id+' · '+(item.name||'Product'))));
        select.append(group);
      }
      const categories=data?.categories||[];
      if(categories.length){
        const group=$('<optgroup label="WooCommerce categories"></optgroup>');
        categories.forEach(item=>group.append($('<option></option>').val('category:'+item.id).text(item.name||('Category #'+item.id))));
        select.append(group);
      }
      if(current && !select.find('option').filter(function(){return $(this).val()===current;}).length){
        select.append($('<option></option>').val(current).text('Saved destination: '+current));
      }
      select.val(current);
    };
    const defaults={
      pages:[
        {value:'page:home',label:'Home page'},
        {value:'page:categories',label:'Categories page'},
        {value:'page:product',label:'Product page'},
        {value:'page:account',label:'Account page'},
        {value:'page:wishlist',label:'Wishlist page'},
        {value:'page:cart',label:'Cart page'}
      ],
      products:liveCache.products||[]
    };
    fill(liveCache.targets||defaults);
    select.on('change',function(){onChange($(this).val());});
    if(!liveCache.targets){
      request('studio/targets','GET').then(data=>{
        liveCache.targets=data;
        fill(data);
      }).catch(()=>{});
    }
    wrap.append(select);
    if(hint)$('<div class="description"></div>').text(hint).appendTo(wrap);
    return wrap;
  }

  function sliderApiPath(key){return 'studio/sliders/'+encodeURIComponent(key);}
  function loadLiveSlider(section,force=false){
    const key=String(section.slider_key||'').trim();
    if(!key)return Promise.resolve(null);
    if(liveCache.sliders[key]&&!force)return Promise.resolve(liveCache.sliders[key]);
    if(liveCache.sliderPending[key])return liveCache.sliderPending[key];
    const pending=request(sliderApiPath(key),'GET').then(data=>{
      liveCache.sliders[key]={...data,dirty:false};
      renderPreview();
      if(selected()?.id===section.id)renderInspector();
      return liveCache.sliders[key];
    }).catch(error=>{
      toast('Slider data could not load.',true);
      throw error;
    }).finally(()=>{delete liveCache.sliderPending[key];});
    liveCache.sliderPending[key]=pending;
    return pending;
  }
  function markSliderDirty(){
    const current=selected();
    const key=current?.slider_key;
    if(key&&liveCache.sliders[key])liveCache.sliders[key].dirty=true;
    markDirty();
  }
  function sliderSettingField(slider,label,key,type='text'){
    const wrap=$('<div class="ark-field ark-slider-setting"></div>');
    $('<label></label>').text(label).appendTo(wrap);
    let input;
    if(type==='checkbox'){
      input=$('<input type="checkbox">').prop('checked',!!slider.settings[key]);
    }else if(type==='select'){
      input=$('<select></select>');
      const options={
        transition:[['slide','Slide'],['fade','Fade'],['zoom','Zoom']],
        image_fit:[['cover','Cover'],['contain','Contain'],['fill','Stretch']],
        pagination:[['dots','Dots'],['progress','Progress bar'],['none','Hidden']]
      }[key]||[];
      options.forEach(([v,l])=>input.append($('<option></option>').val(v).text(l)));
      input.val(slider.settings[key]??options[0]?.[0]??'');
    }else{
      input=$('<input>').attr('type',type).val(slider.settings[key]??'');
    }
    input.on('input change',function(){
      slider.settings[key]=type==='checkbox'?$(this).is(':checked'):(type==='number'?Number($(this).val()):$(this).val());
      markSliderDirty();renderPreview();
    });
    wrap.append(input);return wrap;
  }
  function slideTextField(slide,label,key,type='text'){
    const wrap=$('<div class="ark-field ark-slide-meta-field"></div>');
    $('<label></label>').text(label).appendTo(wrap);
    const input=$('<input>').attr('type',type).val(slide[key]||'');
    if(type==='datetime-local')input.val(String(slide[key]||'').replace(' ','T').slice(0,16));
    input.on('input change',function(){slide[key]=$(this).val();markSliderDirty();});
    wrap.append(input);return wrap;
  }
  function renderLiveSliderInspector(section){
    if(!section.slider_key){
      section.slider_key=('slider-'+String(section.id||Date.now())).toLowerCase().replace(/[^a-z0-9_-]/g,'-').slice(0,64);
      markDirty();
    }
    const key=section.slider_key;
    const slider=liveCache.sliders[key];
    $('#ark-inspector-title').text('ARK Live Slider · SQL storage');
    if(!slider){
      $('<div class="ark-slider-loading"></div>').text('Loading saved slider settings and images…').appendTo($inspector);
      loadLiveSlider(section).catch(()=>{});return;
    }
    slider.settings=slider.settings||{};
    slider.slides=Array.isArray(slider.slides)?slider.slides:[];
    $('<p class="description"></p>').text('Images stay in WordPress Media/Uploads. Slide data, links, order and schedules are saved in dedicated SQL tables, not Designer JSON.').appendTo($inspector);
    $inspector.append(sliderSettingField(slider,'Slider enabled','enabled','checkbox'));
    $inspector.append(sliderSettingField(slider,'Auto play','autoplay','checkbox'));
    $inspector.append(sliderSettingField(slider,'Slide duration (ms)','interval_ms','number'));
    $inspector.append(sliderSettingField(slider,'Infinite loop','loop_slides','checkbox'));
    $inspector.append(sliderSettingField(slider,'Transition animation','transition','select'));
    $inspector.append(sliderSettingField(slider,'Slider height (px)','height_px','number'));
    $inspector.append(sliderSettingField(slider,'Image fit','image_fit','select'));
    $inspector.append(sliderSettingField(slider,'Card width (0.45–1)','card_width','number'));
    $inspector.append(sliderSettingField(slider,'Gap between slides (px)','slide_gap','number'));
    $inspector.append(sliderSettingField(slider,'Previous / next arrows','show_arrows','checkbox'));
    $inspector.append(sliderSettingField(slider,'Pagination','pagination','select'));
    $inspector.append(sliderSettingField(slider,'Pause on interaction','pause_on_interaction','checkbox'));
    $inspector.append(sliderSettingField(slider,'Corner radius (px)','radius','number'));
    const wrap=$('<div class="ark-slide-editor ark-live-slide-list"></div>');
    const head=$('<div class="ark-slide-editor-head"></div>');
    $('<b></b>').text('SQL SAVED SLIDES').appendTo(head);
    $('<span></span>').text(slider.slides.length+' slides').appendTo(head);
    wrap.append(head);
    slider.slides.forEach((slide,index)=>{
      const card=$('<div class="ark-slide-item ark-live-slide-item"></div>');
      const cardHead=$('<div class="ark-live-slide-heading"></div>');
      $('<b></b>').text('SLIDE '+(index+1)).appendTo(cardHead);
      $('<button type="button" class="ark-slide-order">↑</button>').prop('disabled',index===0).on('click',()=>{
        [slider.slides[index-1],slider.slides[index]]=[slider.slides[index],slider.slides[index-1]];
        markSliderDirty();renderInspector();
      }).appendTo(cardHead);
      $('<button type="button" class="ark-slide-order">↓</button>').prop('disabled',index===slider.slides.length-1).on('click',()=>{
        [slider.slides[index+1],slider.slides[index]]=[slider.slides[index],slider.slides[index+1]];
        markSliderDirty();renderInspector();
      }).appendTo(cardHead);
      const active=$('<label class="ark-slide-active"><input type="checkbox"> Active</label>');
      active.find('input').prop('checked',slide.active!==false).on('change',function(){slide.active=$(this).is(':checked');markSliderDirty();});
      cardHead.append(active);card.append(cardHead);
      if(slide.image_url)$('<img class="ark-live-slide-thumb" alt="">').attr('src',slide.image_url).appendTo(card);
      else $('<div class="ark-live-slide-thumb ark-live-slide-empty"></div>').text('Choose image').appendTo(card);
      $('<button type="button" class="ark-media-pick">Choose / replace image</button>').on('click',function(){
        const frame=wp.media({title:'Choose slider image',button:{text:'Use image'},multiple:false,library:{type:'image'}});
        frame.on('select',function(){
          const media=frame.state().get('selection').first().toJSON();
          slide.attachment_id=Number(media.id)||0;slide.image_url=media.url;
          slide.alt_text=media.alt||slide.alt_text||media.title||'';
          markSliderDirty();renderInspector();renderPreview();
        });frame.open();
      }).appendTo(card);
      card.append(slideTextField(slide,'Image title','title'));
      card.append(slideTextField(slide,'Subtitle','subtitle'));
      card.append(slideTextField(slide,'Image alt text','alt_text'));
      card.append(slideTextField(slide,'CTA button label','button_label'));
      const mode=$('<div class="ark-field"><label>Tap action type</label><select><option value="in_app">In-app destination</option><option value="deeplink">App deep link</option><option value="chrome">Open website</option></select></div>');
      mode.find('select').val(slide.link_type||'in_app').on('change',function(){slide.link_type=$(this).val();markSliderDirty();renderInspector();});
      card.append(mode);
      if((slide.link_type||'in_app')==='in_app'){
        card.append(inAppTargetField('Open page / product / category',slide.link_value||'',value=>{slide.link_value=value;markSliderDirty();}));
      }else{
        card.append(slideTextField(slide,'URL / deep link','link_value',slide.link_type==='chrome'?'url':'text'));
      }
      card.append(slideTextField(slide,'Start showing (optional)','starts_at','datetime-local'));
      card.append(slideTextField(slide,'Stop showing (optional)','ends_at','datetime-local'));
      $('<button type="button" class="ark-slide-remove">Delete slide</button>').on('click',()=>{
        slider.slides.splice(index,1);markSliderDirty();renderInspector();renderPreview();
      }).appendTo(card);
      wrap.append(card);
    });
    $('<button type="button" class="ark-slide-add">＋ Add images from Media Library</button>').on('click',function(){
      const frame=wp.media({title:'Add slider images',button:{text:'Add selected images'},multiple:true,library:{type:'image'}});
      frame.on('select',function(){
        frame.state().get('selection').each(function(attachment){
          const media=attachment.toJSON();
          if(media.url)slider.slides.push({
            attachment_id:Number(media.id)||0,image_url:media.url,title:media.title||'',
            subtitle:'',alt_text:media.alt||'',button_label:'',
            link_type:'in_app',link_value:'page:home',active:true,starts_at:'',ends_at:''
          });
        });
        markSliderDirty();renderInspector();renderPreview();
      });
      frame.open();
    }).appendTo(wrap);
    $('<button type="button" class="ark-btn ark-btn-publish ark-slider-save">Save slider to SQL</button>').on('click',function(){
      const button=$(this).prop('disabled',true).text('Saving images…');
      request(sliderApiPath(key),'POST',{settings:slider.settings,slides:slider.slides})
        .then(result=>{slider.dirty=false;toast('Slider saved · '+result.saved_slides+' slides stored.');return loadLiveSlider(section,true);})
        .catch(error=>toast(error.message||'Slider save failed.',true))
        .finally(()=>button.prop('disabled',false).text('Save slider to SQL'));
    }).appendTo(wrap);
    $inspector.append(wrap);
  }
  function saveDirtyLiveSliders(){
    return Promise.all(Object.entries(liveCache.sliders).filter(([,slider])=>slider?.dirty).map(([key,slider])=>
      request(sliderApiPath(key),'POST',{settings:slider.settings,slides:slider.slides}).then(()=>{slider.dirty=false;})
    ));
  }
  const sourceState={files:[],path:'',sha:'',branch:'',tokenSaved:false};
  function renderSourceEditor(){
    $('#ark-inspector-title').text('GitHub source files');
    $inspector.html(`
      <div class="ark-source-intro">Edit Flutter app source directly in GitHub. Changes are committed to your configured branch; they do not change an installed APK until a new build is released.</div>
      <div class="ark-field"><label>GitHub owner</label><input id="ark-gh-owner" value="arshadzen023-byte"></div>
      <div class="ark-field"><label>Repository</label><input id="ark-gh-repo" value="ark-native-app"></div>
      <div class="ark-field"><label>Working branch</label><input id="ark-gh-branch" value="main"></div>
      <div class="ark-field"><label>Fine-grained GitHub token</label><input id="ark-gh-token" type="password" placeholder="Paste token (saved on WordPress server only)"></div>
      <button class="ark-btn ark-btn-ghost" id="ark-gh-save-settings">Save GitHub access</button>
      <div class="ark-source-divider"></div>
      <div class="ark-field"><label>App source file</label><select id="ark-gh-file"><option value="">Load source file list…</option></select></div>
      <button class="ark-btn ark-btn-ghost" id="ark-gh-load-tree">Refresh file list</button>
      <div class="ark-field"><label>Commit message</label><input id="ark-gh-message" value="Update app source from ARK App Designer"></div>
      <div class="ark-field"><label>Source code</label><textarea id="ark-gh-content" spellcheck="false" placeholder="Select a file to load its source…" style="min-height:280px;font-family:ui-monospace,monospace;font-size:11px;line-height:1.55"></textarea></div>
      <button class="ark-btn ark-btn-publish" id="ark-gh-save-file">Commit file to GitHub</button>
      <p class="description">Allowed roots: flutter-app/ and wordpress-addon/ark-app-designer/. Never paste passwords, Firebase private keys or signing keys into source files.</p>
    `);
    $('#ark-gh-save-settings').on('click',function(){
      const button=$(this).prop('disabled',true).text('Saving…');
      request('studio/github/settings','POST',{
        owner:$('#ark-gh-owner').val(),repo:$('#ark-gh-repo').val(),
        branch:$('#ark-gh-branch').val(),token:$('#ark-gh-token').val()
      }).then(r=>{sourceState.branch=r.branch;$('#ark-gh-token').val('');toast('GitHub access saved securely on WordPress.');loadSourceTree();})
      .catch(e=>toast(e.message,true)).finally(()=>button.prop('disabled',false).text('Save GitHub access'));
    });
    $('#ark-gh-load-tree').on('click',loadSourceTree);
    $('#ark-gh-file').on('change',function(){
      const path=$(this).val();if(!path)return;
      request('studio/github/file?path='+encodeURIComponent(path),'GET').then(file=>{
        sourceState.path=file.path;sourceState.sha=file.sha;sourceState.branch=file.branch;
        $('#ark-gh-content').val(file.content);toast('Loaded '+file.path);
      }).catch(e=>toast(e.message,true));
    });
    $('#ark-gh-save-file').on('click',function(){
      if(!sourceState.path||!sourceState.sha){toast('Select a source file first.',true);return;}
      const button=$(this).prop('disabled',true).text('Committing…');
      request('studio/github/file','POST',{
        path:sourceState.path,sha:sourceState.sha,
        content:$('#ark-gh-content').val(),message:$('#ark-gh-message').val()
      }).then(r=>{sourceState.sha=r.sha;toast('Saved to '+r.branch+' • '+r.path);})
      .catch(e=>toast(e.message,true)).finally(()=>button.prop('disabled',false).text('Commit file to GitHub'));
    });
    loadSourceTree();
  }
  function loadSourceTree(){
    request('studio/github/tree','GET').then(result=>{
      sourceState.files=result.files||[];sourceState.branch=result.branch||'';
      const select=$('#ark-gh-file').empty().append('<option value="">Choose a file…</option>');
      sourceState.files.forEach(file=>select.append($('<option></option>').val(file.path).text(file.path)));
      toast('Loaded '+sourceState.files.length+' source files from GitHub.');
    }).catch(e=>toast(e.message,true));
  }
  function renderInspector(){
    const s=selected();$inspector.empty();
    if(state.view==='source'){renderSourceEditor();return;}
    if(state.view==='app'){
      $('#ark-inspector-title').text('App identity');
      const app=state.config.app||(state.config.app={});
      $inspector.append(field('App display name','name',app.name||'GlowHit'));
      $inspector.append(field('App tagline','tagline',app.tagline||''));
      $inspector.append(field('App display name','name',app.name||'GlowHit'));
      $inspector.append(field('App icon (in-app header)','icon_url',app.icon_url||'','url','Updates the icon shown inside the installed app after its next design sync. Android launcher icon still requires an APK update.'));
      $inspector.append(field('App logo','logo_url',app.logo_url||'','url','Select the logo used by supported app branding and splash fallback.'));
      $inspector.append(field('Splash screen image','splash_image_url',app.splash_image_url||state.config.splash?.image_url||'','url','Full-screen splash image. Choose from WordPress Media Library.'));
      $inspector.append(field('Enable splash screen','splash_enabled',app.splash_enabled!==false,'checkbox'));
      $inspector.append(field('Splash duration (ms)','splash_duration_ms',app.splash_duration_ms||1800,'number'));
      $inspector.append(choiceField('Splash image fit','splash_fit',app.splash_fit||'contain',[['contain','Contain'],['cover','Cover'],['fill','Fill']]));
      $inspector.append(field('Splash background','splash_background',app.splash_background||'#F5F7FB','color'));
      $('<p class="description">All app identity and splash settings are managed here. Publish changes to update supported installed app builds on their next sync. Android launcher icon changes require a new APK.</p>').appendTo($inspector);
      return;
    }
    if(state.view==='theme'){
      $('#ark-inspector-title').text('Theme & colours');
      const t=state.config.theme||(state.config.theme={});
      const productColorDefaults={cart_button_background:'#FFFFFF',cart_button_text:'#496292',buy_button_background:'#FFD814',buy_button_text:'#202733',buy_button_price:'#202733'};
      [['Primary','primary'],['Accent','accent'],['Background','background'],['Surface','surface'],['Text','text'],
       ['Top header background','header_background'],['Top header text / icons','header_text'],
       ['Bottom navigation background','navigation_background'],['Bottom selected icon background','navigation_indicator'],
       ['Add to Cart background','cart_button_background'],['Add to Cart text / icon','cart_button_text'],
       ['Buy Now background','buy_button_background'],['Buy Now text','buy_button_text'],['Buy Now price text','buy_button_price']
      ].forEach(([label,key])=>{
        const wrap=field(label,key,t[key]||productColorDefaults[key]||'#ffffff','color');wrap.find('input').on('input',e=>{t[key]=e.target.value;markDirty();});
        $inspector.append(wrap);
      });
      const headerMotion=$('<div class="ark-field"><label>Header colour animation</label><select><option value="none">Smooth colour transition</option><option value="gradient">Animated gradient style</option><option value="pulse">Soft pulse</option></select></div>');
      headerMotion.find('select').val(t.header_animation||'none').on('change',e=>{t.header_animation=e.target.value;markDirty();renderPreview();});
      $inspector.append(headerMotion);
      $inspector.append(field('Card corner radius','radius',t.radius||18,'number'));
      $inspector.append(field('Font scale','font_scale',t.font_scale||1,'number','Use 0.85 to 1.30.'));
      return;
    }
    if(state.view==='splash'){
      $('#ark-inspector-title').text('Splash screen');
      const sp=state.config.splash||(state.config.splash={});
      const temp={...sp};
      $inspector.append(field('Enable splash screen','enabled',temp.enabled!==false,'checkbox'));
      $inspector.append(field('Splash image URL','image_url',temp.image_url||'','url'));
      $inspector.append(field('Image fit','fit',temp.fit||'contain','select'));
      $inspector.append(field('Background colour','background',temp.background||'#F5F7FB','color'));
      $inspector.append(field('Splash display time (ms)','duration_ms',temp.duration_ms||1800,'number'));
      $('<p class="description">The installed app shows only this image, edge-to-edge, for the selected duration. No title or loading spinner is shown.</p>').appendTo($inspector);
      return;
    }
    if(state.view==='celebration'){
      $('#ark-inspector-title').text('Crackers animation');
      const celebration=state.config.celebration||(state.config.celebration={enabled:false,duration_ms:4200,pages:['home']});
      $inspector.append(field('Enable crackers animation','enabled',celebration.enabled===true,'checkbox'));
      $inspector.append(field('Animation duration (ms)','duration_ms',celebration.duration_ms||4200,'number'));
      $('<p class="description">Decorative celebration particles play on the Home page only. They do not block taps or scrolling.</p>').appendTo($inspector);
      return;
    }
    if(state.view==='navigation'){
      $('#ark-inspector-title').text('Navigation & app menu');
      const nav=state.config.navigation||(state.config.navigation={items:[],visible:true});
      if(!Array.isArray(nav.items))nav.items=[];
      $inspector.append(field('Show bottom navigation','visible',nav.visible!==false,'checkbox'));
      const list=$('<div class="ark-menu-builder"></div>');
      nav.items.forEach((item,i)=>{
        const card=$('<div class="ark-menu-item"></div>');
        $('<b></b>').text('MENU ITEM '+(i+1)).appendTo(card);
        const label=$('<input type="text" placeholder="Label">').val(item.label||'');
        label.on('input',()=>{item.label=label.val();markDirty();});
        const icon=$('<input type="text" placeholder="Icon name">').val(item.icon||'home');
        icon.on('input',()=>{item.icon=icon.val();markDirty();});
        const type=$('<select><option value="in_app">In-app page</option><option value="deeplink">Deep link</option><option value="chrome">Open in Chrome</option></select>').val(item.link_type||'in_app');
        type.on('change',()=>{item.link_type=type.val();renderInspector();markDirty();});
        const target=$('<input type="text" placeholder="Page key / deep link / URL">').val(item.url||item.target||item.key||'home');
        target.on('input',()=>{item.target=target.val();item.url=target.val();markDirty();});
        const animation=$('<select><option value="none">No animation</option><option value="fade">Fade</option><option value="scale">Scale</option><option value="slide">Slide</option></select>').val(item.animation||'none');
        animation.on('change',()=>{item.animation=animation.val();markDirty();});
        card.append(label,icon,type,target,animation);
        $('<button type="button" class="ark-menu-delete">Delete menu item</button>').on('click',()=>{nav.items.splice(i,1);markDirty();renderInspector();}).appendTo(card);
        list.append(card);
      });
      $('<button type="button" class="ark-menu-add">＋ Add menu item</button>').on('click',()=>{nav.items.push({key:'custom-'+Date.now(),label:'New menu',icon:'widgets',link_type:'in_app',target:'home',animation:'fade'});markDirty();renderInspector();}).appendTo(list);
      $inspector.append(list);
      $('<p class="description">In-app page: home/categories/product/account. Deep link: app URL scheme. Chrome: HTTPS website URL.</p>').appendTo($inspector);
      return;
    }
    if(!s){$('#ark-inspector-title').text('Select a section');$inspector.html('<div class="ark-empty-inspector">Choose a component from the canvas to edit its settings.</div>');return;}
    $('#ark-inspector-title').text(ARKDesigner.components[s.type]?.label||s.type);
    $inspector.append(field('Section title','title',s.title||''));
    const layout=$('<details class="ark-layout-settings" open><summary>Size, spacing & position</summary><div class="ark-layout-grid"></div></details>');
    const layoutGrid=layout.find('.ark-layout-grid');
    [
      ['Width (%)','width_pct',s.width_pct??100],['Height (px)','height_px',s.height_px??0],['Minimum height (px)','min_height_px',s.min_height_px??0],['Maximum width (px)','max_width_px',s.max_width_px??0],
      ['Top padding','padding_top',s.padding_top??0],['Right padding','padding_right',s.padding_right??0],
      ['Bottom padding','padding_bottom',s.padding_bottom??0],['Left padding','padding_left',s.padding_left??0],
      ['Top margin','margin_top',s.margin_top??0],['Right margin','margin_right',s.margin_right??0],['Bottom margin','margin_bottom',s.margin_bottom??0],['Left margin','margin_left',s.margin_left??0],
      ['Corner radius','corner_radius',s.corner_radius??18]
    ].forEach(([label,key,value])=>layoutGrid.append(field(label,key,value,'number')));
    layout.appendTo($inspector);
    $inspector.append(choiceField('Horizontal alignment','alignment',s.alignment||'stretch',[
      ['stretch','Full width'],['left','Left'],['center','Center'],['right','Right']
    ]));
    $inspector.append(field('Background colour','section_background',s.section_background||'#FFFFFF','color'));
    $inspector.append(choiceField('Background animation','background_motion',s.background_motion||'none',[
      ['none','Static'],['pulse','Soft pulse'],['gradient','Moving gradient'],['shimmer','Shimmer'],['glow','Glow']
    ]));
    const tapMode=$('<div class="ark-field"><label>Tap action type</label><select><option value="in_app">In-app page / product</option><option value="deeplink">App deep link</option><option value="chrome">Open website</option></select></div>');
    tapMode.find('select').val(s.tap_link_type||'in_app').on('change',function(){
      s.tap_link_type=$(this).val();
      markDirty();
      renderInspector();
    });
    $inspector.append(tapMode);
    if((s.tap_link_type||'in_app')==='in_app'){
      $inspector.append(inAppTargetField('In-app destination',s.tap_url||'',value=>{
        s.tap_url=value;markDirty();
      },'Select a native page or published WooCommerce product.'));
    }else{
      $inspector.append(field(
        'Tap action URL / deep link','tap_url',s.tap_url||'',
        s.tap_link_type==='chrome'?'url':'text',
        s.tap_link_type==='chrome'?'Enter an HTTPS website URL.':'Enter your app deep link.'
      ));
    }
    if(['product_grid','product_carousel','recently_viewed'].includes(s.type)){
      $inspector.append(choiceField('Product card design','card_design',s.card_design||'flipkart',[
        ['flipkart','Flipkart clean'],['classic','Classic'],['elevated','Elevated'],['glass','Soft glass'],['compact','Compact'],['minimal','Minimal']
      ]));
    }
    if(['heading','text','offer_banner'].includes(s.type)){
      $inspector.append(choiceField('Text alignment','text_alignment',s.text_alignment||'left',[
        ['left','Left'],['center','Center'],['right','Right']
      ]));
    }
    if(s.type==='product_delivery'){
      $inspector.append(choiceField('Delivery card size','delivery_style',s.delivery_style||'standard',[
        ['compact','Compact'],['standard','Standard']
      ]));
      $inspector.append(field('Show delivery details','show_delivery_details',s.show_delivery_details!==false,'checkbox'));
      $inspector.append(field('Show PIN badge','show_pin_badge',s.show_pin_badge!==false,'checkbox'));
      $inspector.append(field('Delivery title','delivery_title',s.delivery_title||'Delivery & shipping'));
      $inspector.append(field('Card background','delivery_background',s.delivery_background||'#FFFFFF','color'));
    }
    if(s.type==='account_menu'){
      if(!Array.isArray(s.items)||!s.items.length)s.items=[
        {title:'My Orders',subtitle:'Track and view purchases',icon:'receipt',action:'orders'},
        {title:'My Downloads',subtitle:'Your purchased files',icon:'download',action:'downloads'},
        {title:'Edit Address',subtitle:'Manage delivery address',icon:'location',action:'address'},
        {title:'Edit Account Details',subtitle:'Update your profile',icon:'person',action:'profile'},
        {title:'Sign Out',subtitle:'Sign out of this device',icon:'logout',action:'logout'}
      ];
      const menu=$('<div class="ark-menu-builder"><b>Account page list items</b></div>');
      const drawMenu=()=>{
        menu.find('.ark-menu-item').remove();
        s.items.forEach((item,index)=>{
          const card=$('<div class="ark-menu-item ark-account-item"></div>');
          $('<b></b>').text('LIST ITEM '+(index+1)).appendTo(card);
          const title=$('<input type="text" placeholder="Title">').val(item.title||'');
          title.on('input',()=>{item.title=title.val();markDirty();});
          const subtitle=$('<input type="text" placeholder="Short description">').val(item.subtitle||'');
          subtitle.on('input',()=>{item.subtitle=subtitle.val();markDirty();});
          const icon=$('<input type="text" placeholder="Icon name">').val(item.icon||'widgets');
          icon.on('input',()=>{item.icon=icon.val();markDirty();});
          const action=$('<select><option value="orders">My Orders</option><option value="downloads">Downloads</option><option value="address">Edit Address</option><option value="profile">Edit Profile</option><option value="wishlist">Wishlist</option><option value="cart">Cart</option><option value="home">Home</option><option value="categories">Categories</option><option value="support">Customer Support URL</option><option value="custom">Custom URL / Deep link</option><option value="logout">Sign Out</option></select>').val(item.action||'orders');
          action.on('change',()=>{item.action=action.val();markDirty();});
          const link=$('<input type="text" placeholder="Optional URL / target">').val(item.link||'');
          link.on('input',()=>{item.link=link.val();markDirty();});
          const linkType=$('<select><option value="chrome">Open website</option><option value="deeplink">App deep link</option><option value="in_app">In-app page / product</option></select>').val(item.link_type||'chrome');
          linkType.on('change',()=>{item.link_type=linkType.val();markDirty();});

          const controls=$('<div class="ark-menu-controls"></div>');
          $('<button type="button">↑</button>').prop('disabled',index===0).on('click',()=>{[s.items[index-1],s.items[index]]=[s.items[index],s.items[index-1]];markDirty();drawMenu();}).appendTo(controls);
          $('<button type="button">↓</button>').prop('disabled',index===s.items.length-1).on('click',()=>{[s.items[index+1],s.items[index]]=[s.items[index],s.items[index+1]];markDirty();drawMenu();}).appendTo(controls);
          $('<button type="button" class="ark-menu-delete">Delete</button>').on('click',()=>{s.items.splice(index,1);markDirty();drawMenu();}).appendTo(controls);
          card.append(title,subtitle,icon,action,linkType,link,controls);menu.append(card);
        });
      };
      drawMenu();
      $('<button type="button" class="ark-menu-add">＋ Add account list item</button>').on('click',()=>{s.items.push({title:'New item',subtitle:'',icon:'widgets',action:'custom',link:''});markDirty();drawMenu();}).appendTo(menu);
      $inspector.append(menu);
    }
    if(s.type==='product_gallery'){
      $inspector.append(field('Gallery height (px)','gallery_height',s.gallery_height||390,'number','Recommended: 340–440 px for a large product hero.'));
      $inspector.append(choiceField('Gallery image fit','gallery_fit',s.gallery_fit||'contain',[
        ['contain','Show full product (no crop)'],['cover','Fill gallery (may crop)']
      ]));
      $inspector.append(field('Auto-slide product images','auto_slide',s.auto_slide!==false,'checkbox'));
      $inspector.append(field('Auto-slide interval (ms)','auto_slide_interval_ms',s.auto_slide_interval_ms||4200,'number'));
      $inspector.append(field('Show image pagination dots','show_dots',s.show_dots!==false,'checkbox'));
      $inspector.append(field('Allow tap to full-screen zoom','allow_zoom',s.allow_zoom!==false,'checkbox'));
      $inspector.append(field('Show Wishlist action','show_wishlist',s.show_wishlist!==false,'checkbox'));
      $inspector.append(field('Show Share action','show_share',s.show_share!==false,'checkbox'));
      $inspector.append(choiceField('Floating action layout','action_layout',s.action_layout||'vertical',[
        ['vertical','Vertical (Flipkart-style)'],['horizontal','Horizontal']
      ]));
      $inspector.append(field('Action button size (px)','action_button_size',s.action_button_size||44,'number'));
      $inspector.append(field('Action icon size (px)','action_icon_size',s.action_icon_size||21,'number'));
      $inspector.append(field('Space between actions (px)','action_gap',s.action_gap||9,'number'));
      $inspector.append(field('Top offset (px)','action_offset_top',s.action_offset_top||14,'number'));
      $inspector.append(field('Right offset (px)','action_offset_right',s.action_offset_right||14,'number'));
      $inspector.append(field('Action background','action_background',s.action_background||'#FFFFFF','color'));
      $inspector.append(field('Action icon colour','action_icon_color',s.action_icon_color||'#263248','color'));
    }
    if(s.type==='product_recommended'){
      $inspector.append(field('Auto-slide recommended offers','promo_autoplay',s.promo_autoplay!==false,'checkbox'));
      $inspector.append(field('Auto-slide interval (ms)','promo_interval_ms',s.promo_interval_ms||4000,'number'));
      $inspector.append(field('Offer card height (px)','promo_card_height',s.promo_card_height||112,'number'));
      $inspector.append(field('Offer card width (px)','promo_card_width',s.promo_card_width||238,'number'));
      $inspector.append(field('Offer image size (px)','promo_image_size',s.promo_image_size||72,'number'));
      $inspector.append(field('Show sponsored AD badge','show_ad_badge',s.show_ad_badge!==false,'checkbox'));
      $inspector.append(field('AD badge label','ad_label',s.ad_label||'AD'));
      $inspector.append(field('Show discount percentage','show_discount',s.show_discount!==false,'checkbox'));
      $inspector.append(field('Show original crossed price','show_original_price',s.show_original_price!==false,'checkbox'));
    }
    if(s.type==='product_title'){
      $inspector.append(field('Title font size (px)','font_size',s.font_size||23,'number'));
      $inspector.append(field('Title max lines','max_lines',s.max_lines||3,'number'));
    }
    if(s.type==='product_price'){
      $inspector.append(choiceField('Price card style','price_style',s.price_style||'standard',[
        ['standard','Standard'],['highlight','Highlighted'],['compact','Compact']
      ]));
    }
    if(s.type==='product_delivery'){
      $inspector.append(field('Delivery helper text','delivery_subtitle',s.delivery_subtitle||'Check delivery availability at your address.'));
    }
    if(s.type==='product_description'){
      $inspector.append(field('Description initially expanded','expanded',!!s.expanded,'checkbox'));
    }
    if(s.type==='address_selector'){
      $inspector.append(field('Helper text','subtitle',s.subtitle||'Choose where your order should arrive.'));
      $inspector.append(choiceField('Card style','address_style',s.address_style||'flipkart',[
        ['flipkart','Flipkart delivery card'],['outlined','Outlined'],['compact','Compact']
      ]));
      $inspector.append(field('Show edit button','show_edit',s.show_edit!==false,'checkbox'));
      $inspector.append(field('Show phone number','show_phone',s.show_phone!==false,'checkbox'));
      $inspector.append(field('Show city and PIN','show_city_pin',s.show_city_pin!==false,'checkbox'));
      $inspector.append(field('Allow address switching','allow_address_switch',s.allow_address_switch!==false,'checkbox'));
      $inspector.append(field('Compact on mobile','compact_on_mobile',s.compact_on_mobile===true,'checkbox'));
    }
    if((('subtitle'in s)&&s.type!=='address_selector')||['offer_banner','heading','text'].includes(s.type))$inspector.append(field('Subtitle / text','subtitle',s.subtitle||s.text||'','textarea'));
    if(s.type==='search'){
      $inspector.append(field('Search placeholder','placeholder',s.placeholder||'Search products...'));
      $inspector.append(field('Rotating hints JSON','hints_json',JSON.stringify(s.hints||['Search Watch','Search TV','Search Plugin'],null,2),'textarea','Example: ["Search Watch","Search TV","Search Plugin"]'));
      $inspector.append(field('Hint change speed (ms)','hint_interval_ms',s.hint_interval_ms||2200,'number'));
      $inspector.find('[data-field="hints_json"]').on('change',function(){try{s.hints=JSON.parse($(this).val());markDirty();renderPreview();}catch(e){toast('Hints JSON is invalid.',true);}});
    }
    if(s.type==='image'){
      $inspector.append(field('Image URL','image_url',s.image_url||'','url'));
      $inspector.append(choiceField('Image fit','fit',s.fit||'contain',[
        ['contain','Show full image (no crop)'],['cover','Fill box (may crop)']
      ]));
      $inspector.append(field('Image tap link','button_url',s.button_url||'','text'));
    }
    if(s.type==='offer_banner'){
      $inspector.append(field('Button label','button_label',s.button_label||'Shop now'));
      $inspector.append(field('Button link','button_url',s.button_url||'','text'));
    }
    if(s.type==='button'){
      $inspector.append(field('Button label','button_label',s.button_label||s.title||'Open'));
      $inspector.append(field('Button link','button_url',s.button_url||'','text'));
    }
    if(['product_grid','product_carousel','recently_viewed','category_grid','icon_grid'].includes(s.type)){
      $inspector.append(field('Maximum items','limit',s.limit||8,'number'));
      if(s.type==='product_grid'||s.type==='category_grid'||s.type==='icon_grid')$inspector.append(field('Columns','columns',s.columns||2,'number'));
      if(s.type==='product_grid'||s.type==='product_carousel'){
        const sel=$('<div class="ark-field"><label>Products source</label><select data-field="source"><option value="latest">Latest products</option><option value="featured">Featured products</option><option value="sale">Sale products</option><option value="category">Choose category</option></select></div>');
        sel.find('select').val(s.source||'latest').on('change',e=>{s.source=e.target.value;markDirty();});$inspector.append(sel);
      }
    }
    if(s.type==='product_grid'||s.type==='product_carousel'){
      $inspector.append(field('Destination URL (optional)','destination_url',s.destination_url||'','url','When set, tapping product cards opens this destination instead of product detail.'));
      const mode=$('<div class="ark-field"><label>Destination type</label><select><option value="chrome">Open in Chrome</option><option value="deeplink">Deep link</option><option value="in_app">In-app page</option></select></div>');mode.find('select').val(s.destination_link_type||'chrome').on('change',e=>{s.destination_link_type=e.target.value;markDirty();});$inspector.append(mode);
      $inspector.append(field('Product card width (fraction)','card_width',s.card_width||.46,'number','Horizontal slider card width, e.g. 0.46.'));
      $inspector.append(field('Grid card ratio','card_ratio',s.card_ratio||.66,'number','Grid aspect ratio, 0.45 to 1.2.'));
      const categorySelect=$('<div class="ark-field"><label>Category filter</label><select><option value="0">All categories</option></select></div>');
      (liveCache.categories||[]).forEach(cat=>categorySelect.find('select').append($('<option></option>').val(cat.id).text(cat.name)));
      categorySelect.find('select').val(String(s.category_id||0)).on('change',e=>{s.category_id=Number(e.target.value);markDirty();});
      $inspector.append(categorySelect);
    }
    if(['trust_badges','faq'].includes(s.type)){
      if(!Array.isArray(s.items))s.items=[];
      $inspector.append(field('Items JSON','items_json',JSON.stringify(s.items,null,2),'textarea',s.type==='faq'?'Each item: question and answer.':'Each item: label and icon.'));
      $inspector.find('[data-field="items_json"]').on('change',function(){try{s.items=JSON.parse($(this).val());markDirty();renderPreview();}catch(e){toast('Items JSON is invalid.',true);}});
    }
    if(s.type==='coupon_banner'){
      $inspector.append(field('Coupon code','coupon_code',s.coupon_code||''));
      $inspector.append(field('Background colour','background',s.background||'#FFF4D6','color'));
    }
    if(['button','offer_banner','notice_bar','image'].includes(s.type)){
      $inspector.append(choiceField('Button / image link type','button_link_type',s.button_link_type||'chrome',[
        ['in_app','In-app page / product'],['deeplink','App deep link'],['chrome','Open website']
      ]));
    }
    if(s.type==='notice_bar'){
      $inspector.append(field('Icon name','icon',s.icon||'offer'));
      $inspector.append(field('Background colour','background',s.background||'#eaf1ff','color'));
      $inspector.append(field('Action label','button_label',s.button_label||''));
      $inspector.append(field('Action link','button_url',s.button_url||'','url'));
    }
    if(s.type==='countdown'){
      $inspector.append(field('Deadline (ISO date/time)','end_at',s.end_at||'','datetime-local','The countdown starts from the selected date and time on the device.'));
    }
    if(s.type==='icon_grid'){
      if(!Array.isArray(s.items))s.items=[];
      $inspector.append(field('Icon links JSON (icon, label, link)','items_json',JSON.stringify(s.items,null,2),'textarea','Icons: home, category, favorite, cart, delivery, support, offer, phone, location, download, person, star, bolt.'));
      $inspector.find('[data-field="items_json"]').on('change',function(){
        try{s.items=JSON.parse($(this).val());markDirty();renderPreview();}
        catch(e){toast('Icon links JSON is invalid.',true);}
      });
    }
    if(s.type==='ark_live_slider')renderLiveSliderInspector(s);

    $inspector.append(field('Visible in app','visible',s.visible!==false,'checkbox'));
  }
  function loadLivePreview(){
    if(liveCache.loading)return;liveCache.loading=true;
    const root=(window.location.origin||'').replace(/\/$/,'');
    Promise.all([
      fetch(root+'/wp-json/wc/store/v1/products/categories?per_page=50&hide_empty=false').then(r=>r.ok?r.json():[]).catch(()=>[]),
      fetch(root+'/wp-json/wc/store/v1/products?per_page=100').then(r=>r.ok?r.json():[]).catch(()=>[]),
      request('studio/targets','GET').catch(()=>null)
    ]).then(([cats,products,targets])=>{liveCache.categories=Array.isArray(cats)?cats:[];liveCache.products=Array.isArray(products)?products:[];liveCache.targets=targets;renderPreview();if(state.selected)renderInspector();})
    .finally(()=>{liveCache.loading=false;});
  }
  function render(){renderPages();renderPreview();renderInspector();$('#ark-canvas-title').text(labels[state.page]);}
  function addComponent(type){
    const id='section-'+Date.now();
    const defaults={id,type,visible:true,title:ARKDesigner.components[type]?.label||type};
    if(['product_grid','product_carousel','recently_viewed','category_grid'].includes(type))Object.assign(defaults,{limit:8,columns:2,source:'latest',card_width:.46,card_ratio:.66});
    if(type==='ark_live_slider')Object.assign(defaults,{slider_key:'slider-'+id,tap_link_type:'in_app'});
    if(type==='product_gallery')Object.assign(defaults,{gallery_height:390,gallery_fit:'contain',auto_slide:true,auto_slide_interval_ms:4200,show_dots:true,allow_zoom:true,show_wishlist:true,show_share:true,action_layout:'vertical',action_button_size:44,action_icon_size:21,action_gap:9,action_offset_top:14,action_offset_right:14,action_background:'#FFFFFF',action_icon_color:'#263248'});
    if(type==='product_recommended')Object.assign(defaults,{promo_autoplay:true,promo_interval_ms:4000,promo_card_height:112,promo_card_width:238,promo_image_size:72,show_ad_badge:true,ad_label:'AD',show_discount:true,show_original_price:true});
    if(type==='search')Object.assign(defaults,{placeholder:'Search products...',hints:['Search Watch','Search TV','Search Plugin'],hint_interval_ms:2200});
    if(type==='product_grid'||type==='product_carousel')Object.assign(defaults,{link_type:'product',destination_url:'',category_id:0});
    if(type==='address_selector')Object.assign(defaults,{title:'Delivery address',subtitle:'Choose where your order should arrive.',address_style:'flipkart',show_edit:true,show_phone:true,show_city_pin:true,allow_address_switch:true,compact_on_mobile:false});
    if(type==='account_menu')Object.assign(defaults,{items:[{title:'My Orders',subtitle:'Track and view purchases',icon:'receipt',action:'orders'},{title:'My Downloads',subtitle:'Your purchased files',icon:'download',action:'downloads'},{title:'Edit Address',subtitle:'Manage delivery address',icon:'location',action:'address'},{title:'Edit Account Details',subtitle:'Update your profile',icon:'person',action:'profile'},{title:'Sign Out',subtitle:'Sign out of this device',icon:'logout',action:'logout'}]});
    sections().push(defaults);state.selected=id;state.view='page';markDirty();render();
  }
  function request(path,method,data){
    return fetch(ARKDesigner.restUrl+path,{method,headers:{'Content-Type':'application/json','X-WP-Nonce':ARKDesigner.nonce},body:data?JSON.stringify(data):undefined,credentials:'same-origin'}).then(async r=>{const j=await r.json();if(!r.ok)throw new Error(j.message||'Request failed');return j;});
  }
  $('#ark-toggle-left').on('click',function(){
    const workspace=$('.ark-workspace');
    collapseState.left=!collapseState.left;workspace.toggleClass('ark-left-collapsed',collapseState.left);
    $(this).attr('aria-label',collapseState.left?'Expand components panel':'Collapse components panel').attr('aria-expanded',String(!collapseState.left));
  });
  $('#ark-toggle-right').on('click',function(){
    const workspace=$('.ark-workspace');
    collapseState.right=!collapseState.right;workspace.toggleClass('ark-right-collapsed',collapseState.right);
    $(this).attr('aria-label',collapseState.right?'Expand properties panel':'Collapse properties panel').attr('aria-expanded',String(!collapseState.right));
  });
  $('#ark-pages').on('click','button',function(){state.page=$(this).data('page');state.selected=null;state.view='page';render();});
  $('#ark-components').on('click','button',function(){addComponent($(this).data('type'));});
  $('.ark-quick-link').on('click',function(){state.view=$(this).data('view');state.selected=null;renderInspector();});
  $preview.on('click','[data-move]',function(){
    const id=$(this).data('id'),i=sections().findIndex(s=>s.id===id),n=$(this).data('move')==='up'?i-1:i+1;
    if(i<0||n<0||n>=sections().length)return;
    const a=sections();[a[i],a[n]]=[a[n],a[i]];state.selected=id;markDirty();render();
  });
  $preview.on('click','[data-delete]',function(){
    const id=$(this).data('delete');page().sections=sections().filter(s=>s.id!==id);state.selected=null;markDirty();render();
  });
  function validateConfig(publish){
    if(!state.config || typeof state.config!=='object') throw new Error('Designer configuration is empty.');
    if(!state.config.app || !String(state.config.app.name||'').trim()) throw new Error('Enter an App display name in App Identity.');
    const allowed=new Set(Object.keys(ARKDesigner.components||{}));
    for(const [pageKey,pageData] of Object.entries(state.config.pages||{})){
      if(!pageData || !Array.isArray(pageData.sections)) throw new Error('Page '+pageKey+' has an invalid component list.');
      pageData.sections.forEach((section,index)=>{
        if(!section || !allowed.has(section.type)) throw new Error('Unknown component on '+pageKey+' at position '+(index+1)+'.');
        if(section.type==='ark_live_slider' && !/^[a-z0-9_-]{1,64}$/i.test(String(section.slider_key||''))) {
          throw new Error('Live Slider on '+pageKey+' needs a valid Slider ID.');
        }
      });
    }
    return true;
  }
  function save(publish){
    try{validateConfig(publish);}catch(error){toast(error.message,true);return;}
    const button=publish?$('#ark-publish'):$('#ark-save-draft');button.prop('disabled',true).text('Saving…');
    saveDirtyLiveSliders().then(()=>{
      return request(publish?'studio/publish':'studio/draft','POST',state.config);
    }).then(result=>{
      state.config.version=result.version||state.config.version;state.config.updated_at=result.updated_at||state.config.updated_at;state.dirty=false;
      $('#ark-version-label').text(publish?'Published v'+state.config.version:'Draft saved');
      toast(publish?'Published to all supported app clients.':'Draft saved.');
    }).catch(e=>toast(e.message,true)).finally(()=>button.prop('disabled',false).text(publish?'Publish changes ↗':'Save draft'));
  }
  $('#ark-save-draft').on('click',()=>save(false));
  $('#ark-publish').on('click',()=>save(true));
  $('#ark-preview-refresh').on('click',renderPreview);
  $('#ark-version-label').text('Published v'+(state.config.version||1)+' · Draft editor');
  renderComponents();render();loadLivePreview();
});