jQuery(function($){
  const state={config:ARKDesigner.config||{},page:'home',selected:null,view:'page',dirty:false};
  const liveCache={categories:null,products:null,targets:null,sliders:{},sliderPending:{},loading:false};
  const $pages=$('#ark-pages'),$components=$('#ark-components'),$preview=$('#ark-preview'),$inspector=$('#ark-inspector-content');
  const labels={home:'Home page',categories:'Categories page',search:'Search page',wishlist:'Wishlist page',product:'Product page',account:'Account page'};
  const fixedPageKeys=Object.keys(labels);
  const collapseState={left:false,right:false};
  const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const customPageKeys=()=>Object.keys(state.config.pages||{}).filter(key=>key.startsWith('custom_')&&state.config.pages[key]?.custom_page===true);
  const isCustomPage=key=>String(key||state.page).startsWith('custom_')&&state.config.pages?.[key||state.page]?.custom_page===true;
  const pageLabel=key=>state.config.pages?.[key]?.title||labels[key]||String(key).replace(/^custom_/,'').replace(/_/g,' ').replace(/\b\w/g,m=>m.toUpperCase());
  const allPageKeys=()=>[...fixedPageKeys,...customPageKeys()];
  const page=()=>state.config.pages[state.page]||(state.config.pages[state.page]={title:pageLabel(state.page),sections:[]});
  const sections=()=>page().sections||(page().sections=[]);
  const headingForCurrentPage=()=>{
    if(isCustomPage()) return page().heading_settings||(page().heading_settings=$.extend(true,{},state.config.default_heading||{}));
    return state.config.default_heading||(state.config.default_heading={});
  };
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
    allPageKeys().forEach(key=>{
      const custom=isCustomPage(key);
      $('<button class="ark-page-btn"></button>')
        .toggleClass('active',state.page===key)
        .toggleClass('ark-custom-page-btn',custom)
        .text((custom?'▧  ':'▣  ')+pageLabel(key))
        .attr('data-page',key)
        .appendTo($pages);
    });
    $('#ark-page-settings').toggle(isCustomPage());
  }
  function renderComponents(){
    $components.empty();
    const search=$('<input type="search" id="ark-component-search" class="ark-component-search" placeholder="⌕  Search components…">');
    $components.append(search);

    Object.entries(ARKDesigner.components||{}).forEach(([type,item])=>{
      if(type==='heading')return; // Headings are part of the Default Heading Bar, not addable page components.
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
    if(s.type==='heading'){
      const heading=$('<div class="ark-heading-preview"></div>').css({display:'flex',alignItems:'center',gap:'9px',padding:'10px',borderRadius:(Number(s.corner_radius)||12)+'px',background:s.section_background||'#fff',backgroundImage:s.background_image_url?'url('+JSON.stringify(s.background_image_url)+')':'none',backgroundSize:'cover',backgroundPosition:'center'});
      if(s.show_icon!==false)$('<span></span>').text('✦').css({fontSize:'20px'}).appendTo(heading);
      if(s.show_title!==false)$('<b></b>').text(s.title||'Heading').appendTo(heading);
      if(s.show_button===true)$('<button type="button"></button>').text((s.button_icon||'arrow_forward')+'  '+(s.button_label||'Explore')).appendTo(heading);
      if(s.subtitle)$('<small></small>').text(s.subtitle).appendTo(heading);
      card.append(heading);
    }else if(s.type==='search_suggestions'){
      const terms=Array.isArray(s.suggestions)?s.suggestions:[];
      const chips=$('<div class="ark-preview-search-terms"></div>');
      terms.slice(0,4).forEach(term=>$('<span></span>').text(term).appendTo(chips));
      card.append(chips);
    }
    if(s.type==='search'){
      $('<div class="ark-real-search"></div>').text('⌕  '+(s.hints?.[0]||s.placeholder||'Search products…')).appendTo(card);
    }else if(s.type==='ark_live_slider'||String(s.type||'').startsWith('banner_')){
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
    }else if(['product_grid','product_carousel','product_list','product_scroll','recently_viewed','category_grid','category_list','category_list_tile','category_scroll','category_slider','icon_grid'].includes(s.type)){
      const isCategory=String(s.type||'').startsWith('category_')||s.type==='category_grid';
      const layout=s.layout_variant||s.category_layout||(s.type==='product_carousel'?'slider':'grid');
      const isSlider=s.type==='product_carousel'||s.type==='product_scroll'||layout==='slider'||layout==='scroll';
      const liveItems=isCategory?(liveCache.categories||[]):(liveCache.products||[]);
      const columns=Math.max(1,Math.min(4,Number(s.columns)||2));
      const grid=$('<div></div>').addClass((layout==='list'||layout==='list_tile')?'ark-preview-layout-list':(isSlider?'ark-preview-layout-slider':'ark-preview-grid'))
        .attr('data-card-design',s.card_design||'flipkart')
        .attr('data-card-animation',s.card_animation||'none')
        .css({'--ark-layout-columns':columns,'--ark-layout-gap':(Number(s.item_gap)||8)+'px'});
      if(liveItems.length){
        liveItems.slice(0,Math.min(6,Number(s.limit||6))).forEach(item=>{
          const tile=$('<div class="ark-preview-product ark-real-tile"></div>');
          const img=item.image||item.images?.[0]?.src||'';
          if(img)$('<img alt="">').attr('src',img).appendTo(tile);
          $('<b></b>').text(item.name||item.title||'').appendTo(tile);
          if(item.price_html||item.prices?.price)$('<small></small>').text(item.price_html?String(item.price_html).replace(/<[^>]*>/g,''):item.prices.price).appendTo(tile);
          grid.append(tile);
        });
      }else{
        const count=Math.max(2,Math.min(6,Number(s.limit||4)));
        for(let i=0;i<Math.min(count,6);i++)$('<div class="ark-preview-product"></div>').text(isCategory?'Loading live category…':'Loading live product…').appendTo(grid);
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
    const h=headingForCurrentPage();
    const appHeader=$('<div class="ark-preview-app-header"></div>').css({
      background:h.background_color||theme.header_background||'#fff',color:h.title_color||theme.header_text||'#202733',
      minHeight:(Number(h.height)||64)+'px',position:'relative',overflow:'hidden',
      borderRadius:'14px',padding:'9px 12px',display:'flex',alignItems:'center',gap:'8px'
    });
    if(h.show_background_image===true&&h.background_image_url){
      appHeader.css({backgroundImage:'url('+JSON.stringify(h.background_image_url)+')',backgroundSize:h.background_image_fit==='contain'?'contain':(h.background_image_fit==='fill'?'100% 100%':'cover'),backgroundPosition:'center',backgroundRepeat:'no-repeat'});
    }
    if(h.background_effect==='glass')appHeader.css({backdropFilter:'blur(12px)',border:'1px solid rgba(255,255,255,.45)',boxShadow:'0 8px 24px rgba(25,45,75,.12)'});
    if(h.background_effect==='glow')appHeader.css({boxShadow:'0 0 22px '+(h.background_color||'#8AB4F8')});
    if(h.background_effect==='shimmer')appHeader.addClass('ark-heading-shimmer');
    if(h.show_app_logo!==false&&app.icon_url)$('<img alt="">').attr('src',app.icon_url).css({width:'30px',height:'30px',objectFit:'cover',borderRadius:'8px',marginRight:'2px'}).appendTo(appHeader);
    if(h.show_icon===true)$('<span></span>').text(h.icon_name||'storefront').css({marginRight:'2px',fontWeight:800,color:h.icon_color||'#202733'}).appendTo(appHeader);
    if(h.show_title!==false)$('<b></b>').text(h.title||app.name||pageLabel(state.page)).css({flex:'1',minWidth:0,overflow:'hidden',textOverflow:'ellipsis',whiteSpace:'nowrap'}).appendTo(appHeader);
    if(h.show_button===true)$('<button type="button"></button>').text((h.button_icon||'arrow_forward')+' '+(h.button_label||'Explore')).css({marginLeft:'auto',border:0,borderRadius:'9px',padding:'8px 10px',background:h.button_background||'#2874F0',color:h.button_text_color||'#fff'}).appendTo(appHeader);
    const headingCard=$('<div class="ark-preview-card ark-preview-heading-card"></div>').attr('data-id','__default_heading__');
    $('<div class="ark-preview-label"></div>').text(isCustomPage()?'Custom page heading · drag to position':'Default Heading Bar · drag to position').appendTo(headingCard);
    headingCard.append(appHeader);
    const visibleSections=sections().map((section,index)=>({section,index})).filter(entry=>entry.section.visible!==false);
    const pageKey=state.page;
    const headingIsVisible=h.enabled!==false && (Array.isArray(h.visible_pages)?h.visible_pages.includes(pageKey):true);
    const rawPosition=Number(h.position_index ?? -1);
    const insertAt=rawPosition<0?0:Math.min(rawPosition,visibleSections.length);
    if(headingIsVisible){
      if(rawPosition<0)$preview.append(headingCard);
    }
    visibleSections.forEach((entry,visibleIndex)=>{
      if(headingIsVisible && rawPosition>=0 && visibleIndex===insertAt)$preview.append(headingCard);
      $preview.append(previewSection(entry.section,entry.index));
    });
    if(headingIsVisible && rawPosition>=0 && insertAt>=visibleSections.length)$preview.append(headingCard);
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
          const orderedVisible=[];
          let headingPosition=-1;
          let seenVisible=0;
          $preview.children('.ark-preview-card').each(function(){
            const id=$(this).attr('data-id');
            if(id==='__default_heading__'){headingPosition=seenVisible;return;}
            const item=sections().find(s=>s.id===id);
            if(item){orderedVisible.push(item);seenVisible++;}
          });
          const hidden=sections().filter(item=>item.visible===false);
          if(orderedVisible.length===sections().filter(item=>item.visible!==false).length){
            page().sections=[...orderedVisible,...hidden];
            const heading=headingForCurrentPage();
            heading.position_index=headingPosition<=0?-1:headingPosition;
            markDirty();
          }
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
      let targetKey=key;
      if(state.view==='theme') target=state.config.theme||(state.config.theme={});
      if(state.view==='celebration') target=state.config.celebration||(state.config.celebration={enabled:false,duration_ms:4200,pages:['home']});
      if(state.view==='loading') target=state.config.loading||(state.config.loading={enabled:true,animation_url:'',duration_ms:900,size:58,pages:['home','categories','product','account']});
      if(state.view==='splash' && targetKey.startsWith('loading_')){
        target=state.config.loading||(state.config.loading={enabled:true,animation_url:'',duration_ms:900,size:58,pages:['home','categories','product','account']});
        targetKey=targetKey.substring(8);
      }else if(state.view==='splash') target=state.config.splash||(state.config.splash={});
      if(state.view==='navigation') target=state.config.navigation||(state.config.navigation={});
      if(state.view==='app') target=state.config.app||(state.config.app={});
      if(state.view==='default_heading') target=state.config.default_heading||(state.config.default_heading={enabled:true,visible_pages:['home','categories','wishlist','account']});
      if(state.view==='page_settings' && targetKey==='page_title'){target=page();targetKey='title';}
      else if(state.view==='page_settings') target=page().heading_settings||(page().heading_settings=$.extend(true,{},state.config.default_heading||{}));
      if(!target)return;
      const fieldValue=type==='checkbox'?$(this).is(':checked'):(type==='number'?Number($(this).val()):$(this).val());
      target[targetKey]=targetKey==='pages' && (target===state.config.loading||target===state.config.celebration)?String(fieldValue).split(',').map(v=>v.trim()).filter(Boolean):fieldValue;
      markDirty();renderPreview();
    });
    wrap.append(input);
    if(type==='url'&&['image_url','logo_url','icon_url','splash_image_url','background_image_url'].includes(key)){
      const media=$('<button type="button" class="ark-media-pick">Choose from Media Library</button>');
      media.on('click',function(e){
        e.preventDefault();
        const frame=wp.media({title:'Select image',button:{text:'Use image'},multiple:false});
        frame.on('select',function(){
          const item=frame.state().get('selection').first().toJSON();
          const target=state.view==='default_heading'
            ? (state.config.default_heading||(state.config.default_heading={}))
            : state.view==='page_settings'
              ? (page().heading_settings||(page().heading_settings=$.extend(true,{},state.config.default_heading||{})))
              : selected();
          // Persist the attachment ID as the source of truth. WordPress can
          // then re-resolve its canonical URL after a Designer refresh, even
          // if the Media Library serves a resized/CDN URL in the picker.
          if(target && key==='background_image_url'){
            target.background_image_id=Number(item.id)||0;
            target.background_image_url=String(item.url||'');
            target.show_background_image=!!target.background_image_url;
            markDirty();
          }
          input.val(item.url||'').trigger('change');
          renderPreview();
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
      if(state.view==='default_heading') target=state.config.default_heading||(state.config.default_heading={enabled:true,visible_pages:['home','categories','wishlist','account']});
      if(state.view==='page_settings') target=page().heading_settings||(page().heading_settings=$.extend(true,{},state.config.default_heading||{}));
      if(state.view==='loading') target=state.config.loading||(state.config.loading={enabled:true,animation_url:'',duration_ms:900,size:58,pages:['home','categories','product','account']});
      if(state.view==='theme') target=state.config.theme||(state.config.theme={});
      if(state.view==='splash') target=state.config.splash||(state.config.splash={});
      if(!target)return;
      target[key]=$(this).val();markDirty();renderPreview();if(state.view==='default_heading')renderInspector();
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
      const serverCustomPages=Array.isArray(data?.custom_pages)?data.custom_pages:[];
      const customMap=new Map(serverCustomPages.map(item=>[item.value,item]));
      customPageKeys().forEach(key=>customMap.set('page:'+key,{value:'page:'+key,label:pageLabel(key)}));
      const customPages=[...customMap.values()];
      if(customPages.length){
        const group=$('<optgroup label="Custom pages"></optgroup>');
        customPages.forEach(item=>group.append($('<option></option>').val(item.value).text(item.label||item.value)));
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
      products:liveCache.products||[],
      custom_pages:customPageKeys().map(key=>({value:'page:'+key,label:pageLabel(key)}))
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
    if(state.view==='icon_visibility'){
      $('#ark-inspector-title').text('Icon visibility by page');
      const app=state.config.app||(state.config.app={});
      const map=app.icon_visibility||(app.icon_visibility={});
      const pages=allPageKeys();
      const iconsByPage={home:['app_icon','app_name','page_title','refresh','notifications','cart','default_heading_icon','default_heading_title','default_heading_button','voice_search','camera_search'],categories:['app_icon','app_name','page_title','refresh','notifications','cart','default_heading_icon','default_heading_title','default_heading_button'],wishlist:['app_icon','app_name','page_title','refresh','notifications','cart','default_heading_icon','default_heading_title','default_heading_button'],account:['app_icon','app_name','page_title','refresh','notifications','cart','default_heading_icon','default_heading_title','default_heading_button'],search:['cart','voice_search','camera_search'],product:['page_title','cart','wishlist','share','add_to_cart','buy_now']};
      customPageKeys().forEach(key=>{iconsByPage[key]=['app_icon','app_name','page_title','refresh','notifications','cart','default_heading_icon','default_heading_title','default_heading_button'];});
      const panel=$('<div class="ark-icon-visibility"></div>');
      pages.forEach(pageKey=>{
        const group=$('<div class="ark-icon-page"></div>');
        $('<b></b>').text(pageLabel(pageKey)+' page').appendTo(group);
        (iconsByPage[pageKey]||['cart']).forEach(iconKey=>{
          if(!map[pageKey])map[pageKey]={};
          const label=$('<label></label>');
          const cb=$('<input type="checkbox">').prop('checked',map[pageKey][iconKey]!==false);
          cb.on('change',()=>{map[pageKey][iconKey]=cb.is(':checked');markDirty();renderPreview();});
          label.append(cb,document.createTextNode(' '+iconKey.charAt(0).toUpperCase()+iconKey.slice(1)));
          group.append(label);
        });
        panel.append(group);
      });
      $inspector.append(panel);
      return;
    }
    if(state.view==='default_heading' || state.view==='page_settings'){
      const custom=isCustomPage() && state.view==='page_settings';
      $('#ark-inspector-title').text(custom?'Custom Page · Heading & Settings':'Default Heading Bar');
      const h=headingForCurrentPage();
      if(custom)$inspector.append(field('Page name','page_title',page().title||pageLabel(state.page)));
      $inspector.append(field('Enable heading bar','enabled',h.enabled!==false,'checkbox'));
      $inspector.append(field('Show existing app logo','show_app_logo',h.show_app_logo!==false,'checkbox'));
      $inspector.append(field('Show existing app name','show_app_name',h.show_app_name!==false,'checkbox'));
      $inspector.append(field('Show Material Icon','show_icon',h.show_icon===true,'checkbox'));
      $inspector.append(field('Material Icon name','icon_name',h.icon_name||'storefront','text','Examples: storefront, home, category, local_offer, bolt, verified, support_agent.'));
      $inspector.append(field('Show heading title','show_title',h.show_title!==false,'checkbox'));
      if(!custom)$inspector.append(field('Heading title (blank = page/app name)','title',h.title||''));
      $inspector.append(field('Heading title colour','title_color',h.title_color||'#202733','color'));
      $inspector.append(field('Heading icon colour','icon_color',h.icon_color||'#202733','color'));
      $inspector.append(field('Default heading background colour','background_color',h.background_color||'#FFFFFF','color'));
      $inspector.append(field('Show background image','show_background_image',h.show_background_image===true,'checkbox'));
      $inspector.append(field('Background image / GIF','background_image_url',h.background_image_url||'','url','Choose from Media Library. Attachment ID and URL are saved for reliable restore after refresh.'));
      $inspector.append(choiceField('Background image fit','background_image_fit',h.background_image_fit||'cover',[['cover','Cover'],['contain','Contain'],['fill','Stretch']]));
      $inspector.append(field('Background image opacity (%)','background_image_opacity',h.background_image_opacity??100,'number','0 hides the image; 100 shows it fully.'));
      $inspector.append(choiceField('Background effect','background_effect',h.background_effect||'none',[
        ['none','None'],['glass','Glass'],['blur','Blur'],['shimmer','Shimmer'],['glow','Glow'],['gradient','Gradient'],['fireworks','Fireworks / Crackers']
      ]));
      $inspector.append(field('Heading height (px)','height',h.height||64,'number'));
      $inspector.append(field('Heading position (component order)','position_index',Number(h.position_index??-1),'number','-1 keeps it as the fixed app bar. 0–50 moves it into the scrollable page between components.'));
      $inspector.append(choiceField('Heading bar animation','animation',h.animation||'none',[['none','None'],['pulse','Soft pulse'],['gradient','Animated gradient'],['slide','Gentle slide'],['shimmer','Shimmer'],['float','Float']]));
      $inspector.append(field('Show new action button','show_button',h.show_button===true,'checkbox'));
      $inspector.append(field('Button title','button_label',h.button_label||'Explore'));
      $inspector.append(field('Button Material Icon','button_icon',h.button_icon||'arrow_forward','text','Examples: arrow_forward, chevron_right, shopping_bag, bolt, local_offer.'));
      const mode=h.button_link_type||'in_app';
      $inspector.append(choiceField('Button link type','button_link_type',mode,[['in_app','In-app page / product'],['chrome','Website'],['deeplink','App deep link']]));
      if(mode==='in_app')$inspector.append(inAppTargetField('Button destination',h.button_url||'page:categories',value=>{h.button_url=value;markDirty();renderPreview();},'Select a native page, custom page or WooCommerce product.'));
      else $inspector.append(field('Button URL / deep link','button_url',h.button_url||'',mode==='chrome'?'url':'text'));
      $inspector.append(field('Button background colour','button_background',h.button_background||'#2874F0','color'));
      $inspector.append(field('Button text colour','button_text_color',h.button_text_color||'#FFFFFF','color'));
      $inspector.append(field('Show mini action button','show_mini_button',h.show_mini_button===true,'checkbox'));
      $inspector.append(field('Mini button title','mini_button_label',h.mini_button_label||'More'));
      $inspector.append(field('Mini button Material Icon','mini_button_icon',h.mini_button_icon||'chevron_right','text','Examples: chevron_right, help, support_agent, info, phone.'));
      const miniMode=h.mini_button_link_type||'in_app';
      $inspector.append(choiceField('Mini button link type','mini_button_link_type',miniMode,[['in_app','In-app page / product'],['chrome','Website'],['deeplink','App deep link']]));
      if(miniMode==='in_app')$inspector.append(inAppTargetField('Mini button destination',h.mini_button_url||'',value=>{h.mini_button_url=value;markDirty();renderPreview();},'Choose a native page, custom page or WooCommerce product.'));
      else $inspector.append(field('Mini button URL / deep link','mini_button_url',h.mini_button_url||'',miniMode==='chrome'?'url':'text'));
      $inspector.append(field('Mini button background','mini_button_background',h.mini_button_background||'#FFFFFF','color'));
      $inspector.append(field('Mini button text colour','mini_button_text_color',h.mini_button_text_color||'#2874F0','color'));
      const pageWrap=$('<div class="ark-field"><label>Visible on pages</label></div>');
      const pageList=$('<div class="ark-icon-visibility"></div>');
      allPageKeys().forEach(key=>{
        const label=$('<label></label>');
        const cb=$('<input type="checkbox">').prop('checked',Array.isArray(h.visible_pages)?h.visible_pages.includes(key):key!=='search');
        cb.on('change',()=>{
          if(!Array.isArray(h.visible_pages))h.visible_pages=['home','categories','wishlist','account'];
          h.visible_pages=cb.is(':checked')?[...new Set([...h.visible_pages,key])]:h.visible_pages.filter(item=>item!==key);
          markDirty();renderPreview();
        });
        label.append(cb,document.createTextNode(' '+pageLabel(key)));pageList.append(label);
      });
      pageWrap.append(pageList);$inspector.append(pageWrap);
      if(custom){
        const deleteButton=$('<button type="button" class="ark-menu-delete ark-custom-page-delete">Delete this page</button>');
        deleteButton.on('click',function(){
          if(!window.confirm('Delete '+pageLabel(state.page)+' and all its components?'))return;
          const key=state.page;
          delete state.config.pages[key];
          const removeKey=list=>Array.isArray(list)?list.filter(value=>value!==key):list;
          if(state.config.default_heading)state.config.default_heading.visible_pages=removeKey(state.config.default_heading.visible_pages);
          Object.values(state.config.pages||{}).forEach(pageData=>{
            if(Array.isArray(pageData.sections))pageData.sections.forEach(section=>{if(Array.isArray(section.visible_pages))section.visible_pages=removeKey(section.visible_pages);});
          });
          state.page='home';state.selected=null;state.view='page';markDirty();render();
        });
        $inspector.append(deleteButton);
      }
      $('<p class="description">This edits the existing app heading bar. Drag its heading preview card to place it between page components, or set position to -1 to keep it pinned at the top.</p>').appendTo($inspector);
      return;
    }
    if(state.view==='loading'){
      $('#ark-inspector-title').text('Loading animation');
      const loading=state.config.loading||(state.config.loading={enabled:true,animation_url:'',duration_ms:900,size:58,pages:['home','categories','product','account']});
      $inspector.append(field('Enable loading animation','enabled',loading.enabled!==false,'checkbox'));
      $inspector.append(field('Animation JSON URL (blank = bundled local animation)','animation_url',loading.animation_url||'','url'));
      $inspector.append(field('Loading duration (ms)','duration_ms',loading.duration_ms||900,'number'));
      $inspector.append(field('Animation size (px)','size',loading.size||58,'number'));
      const wrap=$('<div class="ark-field"><label>Visible on pages</label></div>');
      const pageList=$('<div class="ark-icon-visibility"></div>');
      allPageKeys().forEach(key=>{
        const label=$('<label></label>');
        const cb=$('<input type="checkbox">').prop('checked',Array.isArray(loading.pages)?loading.pages.includes(key):true);
        cb.on('change',()=>{if(!Array.isArray(loading.pages))loading.pages=[];loading.pages=cb.is(':checked')?[...new Set([...loading.pages,key])]:loading.pages.filter(item=>item!==key);markDirty();});
        label.append(cb,document.createTextNode(' '+pageLabel(key)));pageList.append(label);
      });
      wrap.append(pageList);$inspector.append(wrap);
      $('<p class="description">The bundled Lottie animation is loaded locally. Leave URL blank to use the in-app asset, with a progress indicator fallback if the animation cannot be decoded.</p>').appendTo($inspector);
      return;
    }
    if(state.view==='app'){
      $('#ark-inspector-title').text('App identity');
      const app=state.config.app||(state.config.app={});
      $inspector.append(field('App display name','name',app.name||'GlowHit'));
      $inspector.append(field('App tagline','tagline',app.tagline||''));
      $inspector.append(field('Show app icon','show_icon',app.show_icon!==false,'checkbox'));
      $inspector.append(field('Show app name','show_name',app.show_name!==false,'checkbox'));
      $inspector.append(field('Show Refresh button','show_refresh',app.show_refresh!==false,'checkbox'));
      $inspector.append(field('Show Notifications button','show_notifications',app.show_notifications!==false,'checkbox'));
      $inspector.append(field('Show Cart button','show_cart',app.show_cart!==false,'checkbox'));
      $inspector.append(choiceField('Header icon animation','header_icon_animation',app.header_icon_animation||'none',[['none','None'],['pulse','Pulse'],['rotate','Rotate'],['bounce','Bounce']]));
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
      const navStyle=$('<div class="ark-field"><label>Navigation UI style</label><select><option value="classic">Classic bottom bar</option><option value="pill">Pill navigation</option><option value="floating">Floating rounded bar</option><option value="minimal">Minimal icon bar</option></select></div>');
      navStyle.find('select').val(nav.style||'classic').on('change',e=>{nav.style=e.target.value;markDirty();renderPreview();});
      $inspector.append(navStyle);
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
        let target;
        if((item.link_type||'in_app')==='in_app'){
          const savedTarget=String(item.target||item.url||item.key||'home');
          const normalizedTarget=savedTarget.startsWith('page:')||savedTarget.startsWith('product:')?savedTarget:'page:'+savedTarget;
          target=inAppTargetField('Menu destination',normalizedTarget,value=>{item.target=value;item.url=value;markDirty();},'Custom pages created in this Designer are available here automatically.');
        }else{
          target=$('<input type="text" placeholder="Deep link / URL">').val(item.url||item.target||'');
          target.on('input',()=>{item.target=target.val();item.url=target.val();markDirty();});
        }
        const animation=$('<select><option value="none">No animation</option><option value="fade">Fade</option><option value="scale">Scale</option><option value="slide">Slide</option></select>').val(item.animation||'none');
        animation.on('change',()=>{item.animation=animation.val();markDirty();});
        card.append(label,icon,type,target,animation);
        $('<button type="button" class="ark-menu-delete">Delete menu item</button>').on('click',()=>{nav.items.splice(i,1);markDirty();renderInspector();}).appendTo(card);
        list.append(card);
      });
      $('<button type="button" class="ark-menu-add">＋ Add menu item</button>').on('click',()=>{if(nav.items.length>=5){window.alert('You can add up to 5 navigation buttons.');return;}nav.items.push({key:'custom-'+Date.now(),label:'New menu',icon:'widgets',link_type:'in_app',target:'page:home',url:'page:home',animation:'fade'});markDirty();renderInspector();}).appendTo(list);
      $inspector.append(list);
      $('<p class="description">In-app page: home/categories/product/account. Deep link: app URL scheme. Chrome: HTTPS website URL.</p>').appendTo($inspector);
      return;
    }
    if(!s && state.page==='search'){
      $('#ark-inspector-title').text('Search page settings');
      const settings=page().search_settings||(page().search_settings={
        placeholder:'Search products...',show_voice_search:true,show_camera_search:true,
        show_cart:true,voice_locale:'en_IN',top_bar_background:'#D7E6FF',
        search_bar_background:'#FFFFFF',suggestion_limit:8
      });
      const pageField=(label,key,value,type='text',options=[])=>{
        const wrap=$('<div class="ark-field"></div>');
        $('<label></label>').text(label).appendTo(wrap);
        let input;
        if(type==='checkbox')input=$('<input type="checkbox">').prop('checked',value!==false);
        else if(type==='color')input=$('<input type="color">').val(value||'#FFFFFF');
        else if(type==='select'){
          input=$('<select></select>');
          options.forEach(([v,l])=>input.append($('<option></option>').val(v).text(l)));
          input.val(value||options[0]?.[0]||'');
        }else input=$('<input>').attr('type',type).val(value??'');
        input.on('input change',function(){
          settings[key]=type==='checkbox'?$(this).is(':checked'):(type==='number'?Number($(this).val()):$(this).val());
          markDirty();renderPreview();
        });
        wrap.append(input);$inspector.append(wrap);
      };
      pageField('Search placeholder','placeholder',settings.placeholder||'Search products...');
      pageField('Enable voice search','show_voice_search',settings.show_voice_search!==false,'checkbox');
      pageField('Enable camera search','show_camera_search',settings.show_camera_search!==false,'checkbox');
      pageField('Show Cart button','show_cart',settings.show_cart!==false,'checkbox');
      pageField('Voice language','voice_locale',settings.voice_locale||'en_IN','select',[['en_IN','English (India)'],['hi_IN','Hindi (India)']]);
      pageField('Top bar background','top_bar_background',settings.top_bar_background||'#D7E6FF','color');
      pageField('Search field background','search_bar_background',settings.search_bar_background||'#FFFFFF','color');
      pageField('Maximum fast suggestions','suggestion_limit',settings.suggestion_limit||8,'number');
      $('<p class="description">Drag components onto the Search page canvas to control Recent Searches, product suggestions, Recommended Stores and Trending sections. The search field stays fixed at the top while its settings are edited here.</p>').appendTo($inspector);
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
      ['none','Static'],['pulse','Soft pulse'],['gradient','Moving gradient'],['shimmer','Shimmer'],['glow','Glow'],['fireworks','Fireworks / Crackers']
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
        ['flipkart','Flipkart clean'],['classic','Classic'],['elevated','Elevated'],['glass','Soft glass'],['compact','Compact'],['minimal','Minimal'],['3d','3D tilt card'],['3d_glass','3D glass card']
      ]));
      $inspector.append(choiceField('Product card animation','card_animation',s.card_animation||'none',[
        ['none','None'],['fade','Fade in'],['scale','Scale in'],['slide','Slide in'],['pulse','Pulse'],['tilt_3d','Interactive 3D tilt'],['glow','Soft glow']
      ]));
      $inspector.append(field('3D depth','card_depth',s.card_depth??12,'number','Set 0 to disable depth. Range 0–30.'));
      $inspector.append(field('Card radius (px)','card_radius',s.card_radius??14,'number'));
      $inspector.append(field('Card image aspect ratio','card_aspect_ratio',s.card_aspect_ratio??.66,'number','Range 0.48–1.20. Smaller values make a taller card.'));
      $inspector.append(choiceField('Card image fit','card_image_fit',s.card_image_fit||'contain',[
        ['contain','Show full product image'],['cover','Fill and crop']
      ]));
    }
    if(s.type==='search'){
      $inspector.append(field('Search placeholder','placeholder',s.placeholder||'Search products...'));
      $inspector.append(field('Open customizable Search Page','open_search_page',s.open_search_page!==false,'checkbox'));
      $inspector.append(field('Show Voice Search button','show_voice_search',s.show_voice_search!==false,'checkbox'));
      $inspector.append(field('Show Camera Search button','show_camera_search',s.show_camera_search!==false,'checkbox'));
      $('<p class="description">The search box opens the separate Search Page. Voice and camera buttons open that page and start the selected action.</p>').appendTo($inspector);
    }
    if(s.type==='heading'){
      $inspector.append(field('Show heading icon','show_icon',s.show_icon!==false,'checkbox'));
      $inspector.append(field('Material icon name','icon_name',s.icon_name||'local_offer','text','Examples: local_offer, bolt, star, verified, new_releases.'));
      $inspector.append(field('Show heading name','show_title',s.show_title!==false,'checkbox'));
      $inspector.append(field('Show action button','show_button',s.show_button===true,'checkbox'));
      $inspector.append(field('Button title','button_label',s.button_label||'Explore'));
      $inspector.append(choiceField('Button icon','button_icon',s.button_icon||'arrow_forward',[['arrow_forward','Arrow right'],['chevron_right','Chevron right'],['north_east','Open / diagonal'],['open_in_new','Open in new'],['shopping_bag','Shopping bag'],['local_offer','Offer tag'],['bolt','Lightning'],['star','Star'],['visibility','View']]));
      const buttonMode=s.button_link_type||'in_app';
      $inspector.append(choiceField('Button link type','button_link_type',buttonMode,[['in_app','In-app page / product'],['chrome','Website'],['deeplink','App deep link']]));
      if(buttonMode==='in_app')$inspector.append(inAppTargetField('Button destination',s.button_url||'page:home',value=>{s.button_url=value;markDirty();},'Choose the page or product opened by this heading button.'));
      else $inspector.append(field('Button destination URL / deep link','button_url',s.button_url||'',buttonMode==='chrome'?'url':'text'));
      $inspector.append(field('Button background colour','button_background',s.button_background||'#2874F0','color'));
      $inspector.append(field('Button text colour','button_text_color',s.button_text_color||'#FFFFFF','color'));
      $inspector.append(field('Heading background colour','section_background',s.section_background||'#FFFFFF','color'));
      $inspector.append(choiceField('Heading animation','heading_animation',s.heading_animation||'none',[['none','None'],['fade','Fade'],['slide','Slide'],['pulse','Pulse'],['gradient','Animated gradient']]));
      $inspector.append(choiceField('Icon animation','heading_icon_animation',s.heading_icon_animation||'none',[['none','None'],['pulse','Pulse'],['rotate','Rotate'],['bounce','Bounce']]));
      $inspector.append(field('Background image / GIF URL','background_image_url',s.background_image_url||'','url','Animated GIFs play natively as a background image.'));
      const pages=['home','search','categories','product','account','wishlist'];
      const visibility=$('<div class="ark-field"><label>Visible on pages</label><div class="ark-heading-pages"></div></div>');
      pages.forEach(key=>{
        const label=$('<label style="display:flex;gap:8px;align-items:center;margin:6px 0"></label>');
        const cb=$('<input type="checkbox">').prop('checked',!Array.isArray(s.visible_pages)||s.visible_pages.includes(key));
        cb.on('change',()=>{
          const current=Array.isArray(s.visible_pages)?s.visible_pages.slice():pages.slice();
          s.visible_pages=cb.is(':checked')?[...new Set([...current,key])]:current.filter(x=>x!==key);
          markDirty();renderPreview();
        });
        label.append(cb,document.createTextNode(key[0].toUpperCase()+key.slice(1)));visibility.find('.ark-heading-pages').append(label);
      });
      $inspector.append(visibility);
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
    if(s.type==='search_suggestions'){
      $inspector.append(field('Popular search terms JSON','suggestions_json',JSON.stringify(s.suggestions||['mobiles under 15000','mobile 5g','shoes for men'],null,2),'textarea','Enter a JSON array of search terms.'));
      $inspector.find('[data-field="suggestions_json"]').on('change',function(){try{s.suggestions=JSON.parse($(this).val());markDirty();renderPreview();}catch(e){toast('Search terms JSON is invalid.',true);}});
    }
    if(s.type==='search'){
      $inspector.append(field('Search placeholder','placeholder',s.placeholder||'Search products...'));
      $inspector.append(field('Rotating hints JSON','hints_json',JSON.stringify(s.hints||['Search Watch','Search TV','Search Plugin'],null,2),'textarea','Example: ["Search Watch","Search TV","Search Plugin"]'));
      $inspector.append(field('Hint change speed (ms)','hint_interval_ms',s.hint_interval_ms||2200,'number'));
      $inspector.append(field('Open Search page when tapped','open_search_page',s.open_search_page!==false,'checkbox'));
      $inspector.append(field('Fast product suggestions with images','show_fast_suggestions',s.show_fast_suggestions!==false,'checkbox'));
      $inspector.append(field('Enable voice search','show_voice_search',s.show_voice_search!==false,'checkbox'));
      $inspector.append(field('Enable camera / image text search','show_camera_search',s.show_camera_search!==false,'checkbox'));
      $inspector.append(field('Suggestion count','suggestion_limit',s.suggestion_limit||8,'number'));
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
    if(['product_grid','product_carousel','product_list','product_scroll','recently_viewed','category_grid','category_list','category_list_tile','category_scroll','category_slider','icon_grid'].includes(s.type)){
      $inspector.append(field('Maximum items','limit',s.limit||8,'number'));
      if(['product_grid','category_grid','category_list','category_list_tile','category_scroll','category_slider','icon_grid'].includes(s.type))$inspector.append(field('Columns','columns',s.columns||2,'number'));
      if(['category_grid','category_list','category_list_tile','category_scroll','category_slider'].includes(s.type)){
        $inspector.append(choiceField('Category layout','category_layout',s.category_layout||s.layout_variant||'grid',[
          ['grid','Responsive grid'],['slider','Horizontal slider'],['scroll','Horizontal scroll'],['list','Compact list'],['list_tile','List tile']
        ]));
        $inspector.append(choiceField('Category card style','category_card_style',s.category_card_style||'image',[
          ['image','Image cards'],['elevated','Elevated cards'],['minimal','Minimal cards'],['circle','Circular categories']
        ]));
        $inspector.append(field('Show product count','show_category_count',s.show_category_count===true,'checkbox'));
      }
      if(['product_grid','product_carousel','product_list','product_scroll'].includes(s.type)){
        const sel=$('<div class="ark-field"><label>Products source</label><select data-field="source"><option value="latest">Latest products</option><option value="featured">Featured products</option><option value="sale">Sale products</option><option value="top_rated">Top rated</option><option value="category">Choose category</option><option value="wishlist">Wishlist products</option></select></div>');
        sel.find('select').val(s.source||'latest').on('change',e=>{s.source=e.target.value;markDirty();});$inspector.append(sel);
      }
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
    if(s.type==='ark_live_slider'||String(s.type||'').startsWith('banner_'))renderLiveSliderInspector(s);

    // Shared layout system for banner, category and product families.
    if(['banner_grid','banner_list','banner_slider','banner_scroll','category_grid','category_list','category_list_tile','category_scroll','category_slider','product_grid','product_carousel','product_list','product_scroll'].includes(s.type) || (s.type==='ark_live_slider' && !liveCache.sliders[s.slider_key])){
      const group=$('<div class="ark-layout-property-group"></div>');
      $('<div class="ark-layout-property-title">LAYOUT · STYLE · SETTINGS</div>').appendTo(group);
      $('<p class="description">These shared layout properties are saved in the same page config used by the native app renderer.</p>').appendTo(group);
      group.append(field('Section title','title',s.title||''));
      group.append(field('Background colour','section_background',s.section_background||'#FFFFFF','color'));
      group.append(field('Content / text colour','text_color',s.text_color||'#202733','color'));
      group.append(field('Section width (%)','width_pct',s.width_pct??100,'number'));
      group.append(field('Fixed height (px, 0 = auto)','height_px',s.height_px??0,'number'));
      group.append(field('Minimum height (px)','min_height_px',s.min_height_px??0,'number'));
      group.append(field('Maximum width (px, 0 = full)','max_width_px',s.max_width_px??0,'number'));
      group.append(choiceField('Horizontal alignment','alignment',s.alignment||'stretch',[
        ['stretch','Full width'],['left','Left'],['center','Center'],['right','Right']
      ]));
      group.append(field('Padding top (px)','padding_top',s.padding_top??12,'number'));
      group.append(field('Padding right (px)','padding_right',s.padding_right??12,'number'));
      group.append(field('Padding bottom (px)','padding_bottom',s.padding_bottom??12,'number'));
      group.append(field('Padding left (px)','padding_left',s.padding_left??12,'number'));
      group.append(field('Margin top (px)','margin_top',s.margin_top??0,'number'));
      group.append(field('Margin right (px)','margin_right',s.margin_right??0,'number'));
      group.append(field('Margin bottom (px)','margin_bottom',s.margin_bottom??0,'number'));
      group.append(field('Margin left (px)','margin_left',s.margin_left??0,'number'));
      group.append(field('Card / image gap (px)','item_gap',s.item_gap??8,'number'));
      group.append(field('Corner radius (px)','corner_radius',s.corner_radius??16,'number'));
      group.append(field('Card background colour','card_background',s.card_background||'#FFFFFF','color'));
      group.append(choiceField('Background animation','background_motion',s.background_motion||'none',[
        ['none','None'],['pulse','Pulse'],['gradient','Gradient'],['shimmer','Shimmer'],['glow','Glow'],['fireworks','Fireworks']
      ]));
      if(String(s.type||'').startsWith('category_')||s.type==='category_grid'){
        group.append(choiceField('Category layout','category_layout',s.category_layout||s.layout_variant||'grid',[
          ['grid','Grid'],['slider','Horizontal slider'],['scroll','Horizontal scroll'],['list','List'],['list_tile','List tile']
        ]));
        group.append(choiceField('Category card style','category_card_style',s.category_card_style||'image',[
          ['image','Image cards'],['elevated','Elevated'],['minimal','Minimal'],['circle','Circular']
        ]));
        group.append(field('Number of columns','columns',s.columns||4,'number'));
        group.append(field('Show product count','show_category_count',s.show_category_count===true,'checkbox'));
      }
      if(['product_grid','product_carousel','product_list','product_scroll'].includes(s.type)){
        group.append(choiceField('Product card design','card_design',s.card_design||'flipkart',[
          ['flipkart','Flipkart'],['classic','Classic'],['elevated','Elevated'],['glass','Glass'],['compact','Compact'],['minimal','Minimal'],['3d','3D depth'],['3d_glass','3D glass']
        ]));
        group.append(choiceField('Card animation','card_animation',s.card_animation||'none',[
          ['none','None'],['fade','Fade'],['scale','Scale'],['slide','Slide'],['pulse','Pulse'],['glow','Glow'],['tilt_3d','3D tilt']
        ]));
        group.append(field('3D depth','card_depth',s.card_depth??12,'number'));
        group.append(field('Product card ratio','card_aspect_ratio',s.card_aspect_ratio??.66,'number'));
        group.append(choiceField('Image fit','card_image_fit',s.card_image_fit||'contain',[
          ['contain','Contain'],['cover','Cover']
        ]));
      }
      if(s.type==='ark_live_slider'||String(s.type||'').startsWith('banner_')){
        const variant=s.layout_variant||({banner_grid:'grid',banner_list:'list',banner_scroll:'scroll'}[s.type]||'slider');
        group.append(choiceField('Banner layout','layout_variant',variant,[
          ['grid','Banner Grid'],['list','Banner List'],['slider','Banner Slider'],['scroll','Banner Scroll']
        ]));
        group.append(field('Slider height (px)','height_px',s.height_px??178,'number'));
        group.append(field('Autoplay interval (ms)','interval_ms',s.interval_ms??3500,'number'));
        group.append(choiceField('Slide transition','transition',s.transition||'slide',[
          ['slide','Slide'],['fade','Fade'],['zoom','Zoom']
        ]));
      }
      const controls=$('<div class="ark-layout-settings-controls"></div>');
      $('<button type="button">Hide / show</button>').on('click',()=>{
        s.visible=s.visible===false?true:false;markDirty();render(); 
      }).appendTo(controls);
      $('<button type="button">Move up</button>').on('click',()=>{
        const i=sections().findIndex(item=>item.id===s.id);if(i>0){[sections()[i-1],sections()[i]]=[sections()[i],sections()[i-1]];markDirty();render();}
      }).appendTo(controls);
      $('<button type="button">Move down</button>').on('click',()=>{
        const i=sections().findIndex(item=>item.id===s.id);if(i>=0&&i<sections().length-1){[sections()[i+1],sections()[i]]=[sections()[i],sections()[i+1]];markDirty();render();}
      }).appendTo(controls);
      $('<button type="button" class="danger">Remove</button>').on('click',()=>{
        page().sections=sections().filter(item=>item.id!==s.id);state.selected=null;markDirty();render();
      }).appendTo(controls);
      group.append(controls);
      $inspector.prepend(group);
    }
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
  function layoutDockSelectPage(key){
    if(!state.config.pages[key])return;
    state.page=key;state.selected=null;state.view='page';render();
  }
  function layoutDockOpenView(view){
    state.view=view;state.selected=null;renderInspector();renderPreview();
  }
  function layoutDockSelectFirst(type){
    const item=sections().find(section=>section.type===type);
    if(!item){toast('Add a '+(ARKDesigner.components[type]?.label||type)+' component first.',true);return false;}
    state.selected=item.id;state.view='page';render();
    const node=$preview.find('[data-id="'+item.id+'"]');
    if(node.length)node[0].scrollIntoView({behavior:'smooth',block:'center'});
    return true;
  }
  function layoutDockShowPanel(key){
    const panel=$('#ark-layout-dock-panel');
    panel.empty().prop('hidden',false);
    const row=$('<div class="ark-layout-panel-row"></div>');
    const note=(message)=>$('<p class="ark-layout-panel-note"></p>').text(message).appendTo(panel);
    const action=(label,fn,primary=false)=>{
      $('<button type="button"></button>').toggleClass('primary',primary).text(label).on('click',fn).appendTo(row);
    };
    if(key==='components'){
      note('Choose a layout variant. Images come from WordPress Media Library / the existing SQL slider store; product and category cards load from WooCommerce.');
      action('Banner Grid',()=>addComponent('banner_grid'),true);
      action('Banner List',()=>addComponent('banner_list'));
      action('Banner Slider',()=>addComponent('banner_slider'));
      action('Banner Scroll',()=>addComponent('banner_scroll'));
      action('Category Grid',()=>addComponent('category_grid'),true);
      action('Category List',()=>addComponent('category_list'));
      action('Category List Tile',()=>addComponent('category_list_tile'));
      action('Category Scroll',()=>addComponent('category_scroll'));
      action('Category Slider',()=>addComponent('category_slider'));
      action('Product Grid',()=>addComponent('product_grid'),true);
      action('Product List',()=>addComponent('product_list'));
      action('Product Scroll',()=>addComponent('product_scroll'));
      action('Product Slider',()=>addComponent('product_carousel'));
      action('Open all components',()=>{layoutDockOpenView('page');$('#ark-component-search').trigger('focus');$('.ark-sidebar').get(0)?.scrollIntoView({behavior:'smooth',block:'start'});});
      panel.append(row);
      return;
    }
    if(key==='properties'){
      if(!selected()){
        const first=sections().find(section=>section.visible!==false);
        if(first){state.selected=first.id;state.view='page';renderInspector();}
        else note('Select a component on the phone preview, or add a component to this page.');
      }else{
        state.view='page';renderInspector();
        note('Editing: '+(selected()?.title||selected()?.type||'component')+'. Changes update the phone preview immediately.');
      }
      action('Show properties',()=>{$('.ark-inspector').get(0)?.scrollIntoView({behavior:'smooth',block:'start'});},true);
      panel.append(row);
      return;
    }
    if(key==='reorder'){
      note('Reorder components with the arrows below or drag the component title directly in the live phone preview.');
      const list=$('<div class="ark-layout-reorder-list"></div>');
      sections().forEach((item,index)=>{
        const itemRow=$('<div class="ark-layout-reorder-item"></div>');
        $('<strong></strong>').text((index+1)+'. '+(item.title||ARKDesigner.components[item.type]?.label||item.type)).appendTo(itemRow);
        $('<button type="button">↑</button>').prop('disabled',index===0).on('click',()=>{[sections()[index-1],sections()[index]]=[sections()[index],sections()[index-1]];state.selected=item.id;markDirty();render();layoutDockShowPanel('reorder');}).appendTo(itemRow);
        $('<button type="button">↓</button>').prop('disabled',index===sections().length-1).on('click',()=>{[sections()[index+1],sections()[index]]=[sections()[index],sections()[index+1]];state.selected=item.id;markDirty();render();layoutDockShowPanel('reorder');}).appendTo(itemRow);
        list.append(itemRow);
      });
      if(!sections().length)list.append($('<div class="ark-layout-empty"></div>').text('This page has no components yet.'));
      panel.append(list);
      return;
    }
    if(key==='templates'){
      note('My templates are stored in this browser for this administrator. Save a section or the full page, then insert it into the current page. Publishing the inserted template sends it to the same live app configuration.');
      const templatesKey='arkDesignerMyTemplatesV1';
      let templates=[];
      try{templates=JSON.parse(window.localStorage.getItem(templatesKey)||'[]');if(!Array.isArray(templates))templates=[];}catch(_){templates=[];}
      action('Save selected component',()=>{
        const item=selected();if(!item){toast('Select a component first.',true);return;}
        templates.push({id:'tpl-'+Date.now(),name:item.title||ARKDesigner.components[item.type]?.label||item.type,kind:'section',data:JSON.parse(JSON.stringify(item))});
        window.localStorage.setItem(templatesKey,JSON.stringify(templates));toast('Component saved to My templates.');layoutDockShowPanel('templates');
      },true);
      action('Save whole page',()=>{
        templates.push({id:'tpl-'+Date.now(),name:pageLabel(state.page)+' template',kind:'page',data:JSON.parse(JSON.stringify({title:page().title,sections:sections()}))});
        window.localStorage.setItem(templatesKey,JSON.stringify(templates));toast('Page template saved.');layoutDockShowPanel('templates');
      });
      templates.forEach(template=>{
        const templateRow=$('<div class="ark-layout-reorder-item"></div>');
        $('<strong></strong>').text(template.name+' · '+template.kind).appendTo(templateRow);
        $('<button type="button">Insert</button>').on('click',()=>{
          if(template.kind==='section'){
            const item=JSON.parse(JSON.stringify(template.data));item.id='section-'+Date.now();sections().push(item);state.selected=item.id;
          }else{
            const copied=JSON.parse(JSON.stringify(template.data.sections||[]));
            copied.forEach((item,index)=>{item.id='section-'+Date.now()+'-'+index;});
            page().sections=[...sections(),...copied];state.selected=copied[copied.length-1]?.id||null;
          }
          state.view='page';markDirty();render();toast('Template inserted. Save Draft or Publish to sync.');
        }).appendTo(templateRow);
        $('<button type="button">Delete</button>').on('click',()=>{
          templates=templates.filter(item=>item.id!==template.id);window.localStorage.setItem(templatesKey,JSON.stringify(templates));layoutDockShowPanel('templates');
        }).appendTo(templateRow);
        panel.append(templateRow);
      });
      if(!templates.length)panel.append($('<div class="ark-layout-empty"></div>').text('No templates saved yet.'));
      panel.append(row);
      return;
    }
    if(key==='pages'){
      note('Switch the live phone canvas to a page. Create a new page or open the existing page settings.');
      allPageKeys().forEach(pageKey=>action(pageLabel(pageKey),()=>layoutDockSelectPage(pageKey),state.page===pageKey));
      action('＋ New page',openAddPageDialog,true);
      action('Page settings',()=>layoutDockOpenView('page_settings'));
      panel.append(row);
      return;
    }
    if(key==='slide_menu'){
      const nav=state.config.navigation||(state.config.navigation={visible:true,items:[]});
      if(!Array.isArray(nav.items))nav.items=[];
      note('Create an item for the app slide menu. The item is stored in Navigation and uses the same destination picker as the bottom menu.');
      action('＋ Add slide menu item',()=>{
        nav.items.push({key:'drawer-'+Date.now(),label:'New menu',icon:'widgets',link_type:'in_app',target:'page:home',url:'page:home',animation:'fade',placement:'drawer'});
        markDirty();layoutDockOpenView('navigation');layoutDockShowPanel('menu_edit');
      },true);
      action('Edit menu items',()=>layoutDockOpenView('navigation'));
      panel.append(row);
      return;
    }
    if(key==='menu_edit'||key==='navigation'){
      note('Navigation item labels, icons, destinations, animation and four bar styles are edited in the shared Navigation properties. Save Draft, then Publish to sync to GlowHit.');
      action('Open Navigation properties',()=>layoutDockOpenView('navigation'),true);
      action('Add menu button',()=>{
        const nav=state.config.navigation||(state.config.navigation={visible:true,items:[]});
        if(!Array.isArray(nav.items))nav.items=[];
        if(nav.items.length>=5){toast('The native bottom bar supports up to 5 items.',true);return;}
        nav.items.push({key:'custom-'+Date.now(),label:'New menu',icon:'widgets',link_type:'in_app',target:'page:home',url:'page:home',animation:'fade'});
        markDirty();layoutDockOpenView('navigation');
      });
      panel.append(row);
      return;
    }
    if(key==='header'){
      note('Edit the real Home Page Top Header. Header logo, title, background image, colour, actions and animation share the existing Designer configuration.');
      action('Edit top header',()=>layoutDockOpenView('default_heading'),true);
      action('Edit global app identity',()=>layoutDockOpenView('app'));
      panel.append(row);
      return;
    }
    if(key==='search'){
      note('Open the Search page and select its Search component. Search placeholder, voice/camera actions and suggestions are editable in Properties.');
      const searchPage=(page().sections||[]).find(item=>item.type==='search')|| (state.config.pages.search?.sections||[]).find(item=>item.type==='search');
      action('Open Search page',()=>{layoutDockSelectPage('search');if(searchPage)layoutDockSelectFirst('search');},true);
      action('Edit Search settings',()=>{layoutDockSelectPage('search');layoutDockSelectFirst('search');});
      panel.append(row);
      return;
    }
    if(key==='animations'){
      note('Select a banner, category or product component to edit Background animation and Card animation. Header animation is edited under Top Header.');
      const item=selected();
      if(item){
        action('Open selected component animation properties',()=>{state.view='page';renderInspector();$('.ark-inspector').get(0)?.scrollIntoView({behavior:'smooth',block:'start'});},true);
      }else{
        action('Select Product Slider',()=>layoutDockSelectFirst('product_carousel'));
        action('Select Banner Slider',()=>layoutDockSelectFirst('ark_live_slider'));
        action('Select Category Grid',()=>layoutDockSelectFirst('category_grid'));
      }
      panel.append(row);
      return;
    }
    if(key==='account'){
      action('Open Account page',()=>{layoutDockSelectPage('account');},true);
      action('Edit Account menu',()=>{
        layoutDockSelectPage('account');
        const menu=sections().find(item=>item.type==='account_menu');
        if(menu){state.selected=menu.id;state.view='page';render();}
        else toast('Add an Account menu component to this page first.',true);
      });
      panel.append(row);
      return;
    }
    panel.append(row);
  }
  function render(){renderPages();renderPreview();renderInspector();$('#ark-canvas-title').text(pageLabel(state.page));}
  function addComponent(type){
    const id='section-'+Date.now();
    const defaults={id,type,visible:true,title:ARKDesigner.components[type]?.label||type};
    if(['product_grid','product_carousel','product_list','product_scroll','recently_viewed','category_grid','category_list','category_list_tile','category_scroll','category_slider'].includes(type))Object.assign(defaults,{limit:8,columns:2,source:'latest',card_width:.46,card_ratio:.66});
    if(type==='ark_live_slider'||type.startsWith('banner_'))Object.assign(defaults,{slider_key:'slider-'+id,tap_link_type:'in_app',layout_variant:type==='banner_grid'?'grid':(type==='banner_list'?'list':(type==='banner_scroll'?'scroll':'slider'))});
    if(type==='heading')Object.assign(defaults,{show_icon:true,icon_name:'local_offer',show_title:true,text_alignment:'left',heading_animation:'none',heading_icon_animation:'none',visible_pages:[state.page],tap_link_type:'in_app'});
    if(type==='product_gallery')Object.assign(defaults,{gallery_height:390,gallery_fit:'contain',auto_slide:true,auto_slide_interval_ms:4200,show_dots:true,allow_zoom:true,show_wishlist:true,show_share:true,action_layout:'vertical',action_button_size:44,action_icon_size:21,action_gap:9,action_offset_top:14,action_offset_right:14,action_background:'#FFFFFF',action_icon_color:'#263248'});
    if(type==='product_recommended')Object.assign(defaults,{promo_autoplay:true,promo_interval_ms:4000,promo_card_height:112,promo_card_width:238,promo_image_size:72,show_ad_badge:true,ad_label:'AD',show_discount:true,show_original_price:true});
    if(type==='search')Object.assign(defaults,{placeholder:'Search products...',hints:['Search Watch','Search TV','Search Plugin'],hint_interval_ms:2200,open_search_page:true,show_voice_search:true,show_camera_search:true});
    if(['product_grid','product_carousel','product_list','product_scroll'].includes(type))Object.assign(defaults,{link_type:'product',destination_url:'',category_id:0,layout_variant:type==='product_list'?'list':(type==='product_scroll'?'scroll':(type==='product_carousel'?'slider':'grid'))});
    if(['category_grid','category_list','category_list_tile','category_scroll','category_slider'].includes(type))Object.assign(defaults,{layout_variant:type==='category_list'?'list':(type==='category_list_tile'?'list_tile':(type==='category_scroll'?'scroll':(type==='category_slider'?'slider':'grid')))});
    if(type==='address_selector')Object.assign(defaults,{title:'Delivery address',subtitle:'Choose where your order should arrive.',address_style:'flipkart',show_edit:true,show_phone:true,show_city_pin:true,allow_address_switch:true,compact_on_mobile:false});
    if(type==='account_menu')Object.assign(defaults,{items:[{title:'My Orders',subtitle:'Track and view purchases',icon:'receipt',action:'orders'},{title:'My Downloads',subtitle:'Your purchased files',icon:'download',action:'downloads'},{title:'Edit Address',subtitle:'Manage delivery address',icon:'location',action:'address'},{title:'Edit Account Details',subtitle:'Update your profile',icon:'person',action:'profile'},{title:'Sign Out',subtitle:'Sign out of this device',icon:'logout',action:'logout'}]});
    sections().push(defaults);state.selected=id;state.view='page';markDirty();render();
  }
  function openAddPageDialog(){
    if($('#ark-add-page-modal').length)return;
    const modal=$(`
      <div class="ark-modal-backdrop" id="ark-add-page-modal">
        <div class="ark-modal" role="dialog" aria-modal="true" aria-labelledby="ark-add-page-title">
          <h2 id="ark-add-page-title">Add New Page</h2>
          <p>Create a native app page. It will be added to component link destinations after saving.</p>
          <label>Page name</label>
          <input id="ark-new-page-name" type="text" maxlength="40" placeholder="e.g. Offers, About us, Support">
          <div class="ark-modal-actions">
            <button type="button" class="ark-btn ark-btn-ghost" id="ark-cancel-new-page">Cancel</button>
            <button type="button" class="ark-btn ark-btn-publish" id="ark-confirm-new-page">Create page</button>
          </div>
        </div>
      </div>`);
    $('body').append(modal);
    $('#ark-new-page-name').trigger('focus');
    $('#ark-cancel-new-page').on('click',()=>modal.remove());
    $('#ark-confirm-new-page').on('click',()=>{
      const title=String($('#ark-new-page-name').val()||'').trim();
      if(!title){toast('Enter a page name.',true);return;}
      const slug=title.toLowerCase().normalize('NFD').replace(/[\\u0300-\\u036f]/g,'').replace(/[^a-z0-9]+/g,'_').replace(/^_+|_+$/g,'').slice(0,32);
      if(!slug){toast('Use a page name with letters or numbers.',true);return;}
      let key='custom_'+slug, suffix=2;
      while(state.config.pages[key])key='custom_'+slug+'_'+suffix++;
      const heading=$.extend(true,{},state.config.default_heading||{});
      heading.visible_pages=[...new Set([...(heading.visible_pages||[]),key])];
      heading.position_index=-1;
      state.config.pages[key]={title,custom_page:true,heading_settings:heading,sections:[]};
      if(liveCache.targets){if(!Array.isArray(liveCache.targets.custom_pages))liveCache.targets.custom_pages=[];liveCache.targets.custom_pages.push({value:'page:'+key,label:title});}
      state.page=key;state.selected=null;state.view='page';state.dirty=true;
      modal.remove();markDirty();render();
      toast('Created '+title+'. Add components, customize its heading, then Publish changes.');
    });
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
  $('#ark-add-page').on('click',openAddPageDialog);
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
  $('#ark-layout-dock').on('click','[data-layout-tab]',function(){
    const key=String($(this).data('layout-tab')||'');
    $('.ark-layout-tab').removeClass('active');
    $(this).addClass('active');
    layoutDockShowPanel(key);
    if(key==='components')$('.ark-sidebar').get(0)?.scrollIntoView({behavior:'smooth',block:'start'});
    if(key==='properties')$('.ark-inspector').get(0)?.scrollIntoView({behavior:'smooth',block:'start'});
    if(key==='pages')renderPages();
  });
  $('#ark-version-label').text('Published v'+(state.config.version||1)+' · Draft editor');
  // Restore the original visual component alias in the editor after the
  // server has normalized it to the native renderer component type.
  // Published app JSON still receives the normalized base type + layout_variant.
  Object.values(state.config.pages||{}).forEach(pageData=>{
    if(!pageData||!Array.isArray(pageData.sections))return;
    pageData.sections.forEach(section=>{
      if(section?.designer_component_type && ARKDesigner.components?.[section.designer_component_type]){
        section.type=section.designer_component_type;
      }
    });
  });
  let removedLegacyHeadings=0;
  Object.values(state.config.pages||{}).forEach(pageData=>{
    if(!pageData||!Array.isArray(pageData.sections))return;
    const before=pageData.sections.length;
    pageData.sections=pageData.sections.filter(section=>section?.type!=='heading');
    removedLegacyHeadings+=before-pageData.sections.length;
  });
  if(removedLegacyHeadings){
    state.dirty=true;
    $('#ark-version-label').text('Removed obsolete Heading blocks · Publish to sync');
  }
  renderComponents();render();loadLivePreview();
  if(removedLegacyHeadings)toast('Removed '+removedLegacyHeadings+' obsolete Heading component(s). Default Heading Bar is now managed from its own menu.');
});