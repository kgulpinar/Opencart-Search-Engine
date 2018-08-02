(function($) {
    $(document).on('click', '[role="delete-button"]', function(e) {
        if (confirm($(this).attr('data-confirm'))) {
            if ($(this).attr('data-remove')) {
                $(this).closest($(this).attr('data-remove')).remove();
            }
        } else {
            e.stopPropagation();
            e.preventDefault();
        }
    });
}(jQuery));
