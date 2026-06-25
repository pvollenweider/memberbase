(function( $ ) {

    $.fn.datahref = function( options ) {

        var settings = $.extend({
            pointer: true,
            target: '_self'
        }, options );

        this.find('*[data-href]').each(function() {

            var target;
            var inAttributeSettings = {};

            if(settings.pointer) $(this).css('cursor', 'pointer');
            target = settings.target;

            if( $(this).data('href-settings') ) {

                var inAttributeSettings = $(this).data('href-settings');

                if( inAttributeSettings.hasOwnProperty('target') ) target = inAttributeSettings.target;
                if( inAttributeSettings.hasOwnProperty('pointer') ) $(this).css('cursor', inAttributeSettings.pointer);

            }

            if( $(this).data('href-settings-target') ) {
                target = $(this).data('href-settings-target');
            }

            if( $(this).data('href-settings-cursor') ) {
                $(this).css('cursor', $(this).data('href-settings-cursor'));
            }

            $(this).on('click', function(e) {

                e.preventDefault();
                window.open($(this).data('href'), target);

            });

        });
        return this;

    };

}( jQuery ));