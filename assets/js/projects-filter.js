(function($){
  $(document).on('click', '.donation-filter-toggle', function(e){
    e.stopPropagation();
    var $toggle = $(this);
    var $panel = $('#donation-filter-panel');
    $panel.toggleClass('is-open');
    var expanded = $panel.hasClass('is-open');
    $toggle.attr('aria-expanded', expanded ? 'true' : 'false');
    $panel.attr('aria-hidden', expanded ? 'false' : 'true');
  });

  // close panel when clicking outside
  $(document).on('click', function(e){
    var $panel = $('#donation-filter-panel');
    var $toggle = $('.donation-filter-toggle');
    if(!$panel.length || !$panel.hasClass('is-open')) return;
    if($(e.target).closest('#donation-filter-panel, .donation-filter-toggle').length === 0){
      $panel.removeClass('is-open');
      $toggle.attr('aria-expanded','false');
      $panel.attr('aria-hidden','true');
    }
  });

  $(document).on('click', '#donation-filter-apply', function(){
    var selected = [];
    $('#donation-filter-form input[name="cats[]"]:checked').each(function(){
      selected.push($(this).val());
    });
    var base = window.location.href.split('?')[0];
    if (selected.length) {
      window.location = base + '?cats=' + selected.join(',');
    } else {
      window.location = base;
    }
  });

  $(document).on('click', '#donation-filter-clear', function(){
    $('#donation-filter-form input[name="cats[]"]:checked').prop('checked', false);
    // update toggle count after clearing
    $(document).trigger('donation:filters:changed');
  });
})(jQuery);
// update toggle label with selected count
(function($){
  function updateToggleCount(){
    var count = $('#donation-filter-form input[name="cats[]"]:checked').length;
    var $btn = $('.donation-filter-toggle');
    if(!$btn.length) return;
    var base = 'تصفية';
    $btn.text(count ? base + ' (' + count + ')' : base);
  }

  $(document).on('change', '#donation-filter-form input[name="cats[]"]', function(){
    updateToggleCount();
  });

  $(document).ready(function(){ updateToggleCount(); });
  // respond to programmatic changes
  $(document).on('donation:filters:changed', function(){ updateToggleCount(); });
})(jQuery);
