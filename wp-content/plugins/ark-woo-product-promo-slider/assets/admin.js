jQuery(function($){
  function esc(s){ return $('<div>').text(String(s)).html(); }
  $(document).on('input', '.ark-promo-product-search', function(){
    var input=$(this), type=input.data('target'), term=input.val(), results=$('[data-results="'+type+'"]');
    if(term.length<2){results.empty();return;}
    results.text(ARKPromoAdmin.searching);
    $.get(ARKPromoAdmin.ajaxUrl,{action:'ark_promo_search_products',nonce:ARKPromoAdmin.nonce,term:term})
      .done(function(resp){
        results.empty();
        if(!resp || !resp.success || !resp.data.length){results.text(ARKPromoAdmin.noResults);return;}
        resp.data.forEach(function(p){
          var row=$('<button type="button" class="button ark-promo-add-product"></button>')
            .attr('data-id',p.id).attr('data-name',p.name).attr('data-target',type)
            .text(ARKPromoAdmin.add+' — '+p.name+' (#'+p.id+')');
          results.append(row).append(' ');
        });
      }).fail(function(){results.text(ARKPromoAdmin.error);});
  });
  $(document).on('click','.ark-promo-add-product',function(){
    var b=$(this), type=b.data('target'), id=String(b.data('id')), name=String(b.data('name'));
    var chips=$('[data-chips="'+type+'"]');
    if(chips.find('[data-id="'+id+'"]').length) return;
    var field=type==='target'?'ark_promo_target_products[]':'ark_promo_products[]';
    var chip=$('<span class="ark-promo-chip"></span>').attr('data-id',id);
    chip.append(document.createTextNode(name+' '));
    chip.append($('<button type="button" aria-label="Remove">×</button>'));
    chip.append($('<input type="hidden">').attr('name',field).val(id));
    chips.append(chip);
  });
  $(document).on('click','.ark-promo-chip button',function(){$(this).closest('.ark-promo-chip').remove();});
});