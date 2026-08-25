(function($) {
    'use strict';

    $(function() {
        var $member = $('#probg-team-member');

        if (!$member.length || !$.fn.magnificPopup) {
            return;
        }

        $member.magnificPopup({
            delegate: 'a.probg-team-gallery-link',
            type: 'image',
            gallery: {
                enabled: true,
                preload: [0, 1]
            },
            image: {
                titleSrc: function(item) {
                    return item.el.attr('title') || '';
                }
            }
        });
    });
})(jQuery);
