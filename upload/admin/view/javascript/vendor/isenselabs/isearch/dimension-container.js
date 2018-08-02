(function($) {
    $.fn.dimensionContainer = function() {
        return this.each(function(index, element) {
            $(element).find('select').change(function() {
                if ($(this).val() == $(this).attr('data-dimension-hidden')) {
                    $(element).find('[role="dimension-value"]').show();
                    $(element).find('[role="dimension-select"]').removeClass('col-sm-12').addClass('col-sm-6');
                } else {
                    $(element).find('[role="dimension-value"]').hide();
                    $(element).find('[role="dimension-select"]').removeClass('col-sm-6').addClass('col-sm-12');
                }
            }).trigger('change');

            return element;
        });
    };

    $(document).ready(function() {
        $('[role="dimension-container"]').dimensionContainer();
    });
}(jQuery));
