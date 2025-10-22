(function () {
    var DEFAULT_SELECTORS = [
        '#treasury-stack-iframe',
        '#iframe-default',
        'iframe[data-iframe-resizer="true"]'
    ];

    function getIframeByIdOrDefault(id) {
        if (id) {
            var iframeById = document.getElementById(id);
            if (iframeById) {
                return iframeById;
            }
        }

        for (var index = 0; index < DEFAULT_SELECTORS.length; index += 1) {
            var selector = DEFAULT_SELECTORS[index];
            var iframe = document.querySelector(selector);
            if (iframe) {
                return iframe;
            }
        }

        return null;
    }

    function normaliseHeight(height) {
        if (typeof height === 'number') {
            return height;
        }

        if (typeof height === 'string') {
            var parsed = parseInt(height, 10);
            return !isNaN(parsed) ? parsed : null;
        }

        return null;
    }

    function handleMessage(event) {
        var data = event && event.data;

        if (!data || typeof data !== 'object' || data.type !== 'setHeight') {
            return;
        }

        var iframe = getIframeByIdOrDefault(data.id);
        if (!iframe) {
            return;
        }

        var height = normaliseHeight(data.height);
        if (!height || height <= 0) {
            return;
        }

        iframe.style.height = height + 'px';
        iframe.style.overflow = 'hidden';
        iframe.setAttribute('scrolling', 'no');
    }

    window.addEventListener('message', handleMessage);
})();
