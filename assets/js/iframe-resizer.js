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

    function normaliseOrigin(origin) {
        if (typeof origin !== 'string') {
            return null;
        }

        var trimmed = origin.trim();

        if (!trimmed) {
            return null;
        }

        while (trimmed.length > 1 && trimmed.charAt(trimmed.length - 1) === '/') {
            trimmed = trimmed.slice(0, -1);
        }

        return trimmed;
    }

    function getCurrentOrigin() {
        if (window.location && window.location.origin) {
            return window.location.origin;
        }

        if (window.location) {
            return window.location.protocol + '//' + window.location.host;
        }

        return null;
    }

    function getGlobalAllowedOrigins() {
        var config = window.rtIframeResizer;

        if (!config || !config.allowedOrigins) {
            return [];
        }

        if (Array.isArray(config.allowedOrigins)) {
            return config.allowedOrigins;
        }

        if (typeof config.allowedOrigins === 'string') {
            return config.allowedOrigins.split(',');
        }

        return [];
    }

    function getAttributeAllowedOrigins(iframe) {
        if (!iframe || typeof iframe.getAttribute !== 'function') {
            return [];
        }

        var value = iframe.getAttribute('data-iframe-resizer-allowed-origins');

        if (!value) {
            value = iframe.getAttribute('data-iframe-resizer-allowed-origin');
        }

        if (!value || typeof value !== 'string') {
            return [];
        }

        var parts = value.split(',');
        var results = [];

        for (var index = 0; index < parts.length; index += 1) {
            var origin = normaliseOrigin(parts[index]);

            if (origin) {
                results.push(origin);
            }
        }

        return results;
    }

    function getAllowedOrigins(iframe) {
        var origins = [];
        var currentOrigin = normaliseOrigin(getCurrentOrigin());

        if (currentOrigin) {
            origins.push(currentOrigin);
        }

        var globalOrigins = getGlobalAllowedOrigins();

        for (var index = 0; index < globalOrigins.length; index += 1) {
            var globalOrigin = normaliseOrigin(globalOrigins[index]);

            if (globalOrigin) {
                origins.push(globalOrigin);
            }
        }

        var attributeOrigins = getAttributeAllowedOrigins(iframe);

        for (var attrIndex = 0; attrIndex < attributeOrigins.length; attrIndex += 1) {
            var attributeOrigin = attributeOrigins[attrIndex];

            if (attributeOrigin) {
                origins.push(attributeOrigin);
            }
        }

        var uniqueOrigins = [];

        for (var originIndex = 0; originIndex < origins.length; originIndex += 1) {
            var originCandidate = origins[originIndex];
            var exists = false;

            for (var existingIndex = 0; existingIndex < uniqueOrigins.length; existingIndex += 1) {
                if (uniqueOrigins[existingIndex] === originCandidate) {
                    exists = true;
                    break;
                }
            }

            if (!exists) {
                uniqueOrigins.push(originCandidate);
            }
        }

        return uniqueOrigins;
    }

    function isAllowedOrigin(origin, iframe) {
        var normalisedOrigin = normaliseOrigin(origin);

        if (!normalisedOrigin) {
            return false;
        }

        var allowedOrigins = getAllowedOrigins(iframe);

        for (var index = 0; index < allowedOrigins.length; index += 1) {
            if (allowedOrigins[index] === normalisedOrigin) {
                return true;
            }
        }

        return false;
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

        if (!isAllowedOrigin(event && event.origin, iframe)) {
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
