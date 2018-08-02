(function($) {
    $.fn.dropdownSelect = function() {
        return this.each(function(index, element) {
            var name = $(element).attr('data-name');

            var inputExists = function() {
                return $(element).find('input[name="' + name + '"]').length > 0;
            };

            var appendInput = function() {
                $(element).find('.dropdown-menu').append('<input type="hidden" name="' + name + '" value="" />');
            };

            var valueSelected = function() {
                return $(element).find('.dropdown-menu li.active').length > 0;
            };

            var selectFirstValue = function() {
                $(element).find('.dropdown-menu li').first().addClass('active');
            };

            var applyActive = function() {
                $(element).find('input[name="' + name + '"]').val(
                    $(element).find('.dropdown-menu li.active a').attr('data-value')
                );

                $(element).find('*[data-toggle="dropdown"]').html(
                    $(element).find('.dropdown-menu li.active a').html() + '&nbsp;&nbsp;&nbsp;<i class="fa fa-caret-down"></i>'
                );
            };

            if (!inputExists()) {
                appendInput();
            }

            if (!valueSelected()) {
                selectFirstValue();
            }

            applyActive();

            $(element).find('.dropdown-menu li').click(function() {
                $(element).find('.dropdown-menu li').removeClass('active');
                $(this).addClass('active');

                applyActive();
            });

            return element;
        });
    };

    $(document).ready(function() {
        $('[role="dropdown-select"]').dropdownSelect();
    });

    $(document).on('DOMNodeInserted', function(e) {
        if ($(e.target).find('[role="dropdown-select"]').length > 0) {
            $(e.target).find('[role="dropdown-select"]').dropdownSelect();
        }
    });
}(jQuery));
