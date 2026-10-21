/* ============================================================
   HMS scroll reveal (themed public sites)
   Content slides up from below as it enters the viewport and
   fades away again when it scrolls past the top, the way a
   React + Framer Motion page behaves - without a framework.
   ============================================================ */
(function () {
    'use strict';

    var SELECTORS = [
        '.hms-hero-inner',
        '.hms-page-hero .container',
        '.hms-section .heading',
        '.hms-inner .heading',
        '.hms-fact',
        '.hms-about-row > div',
        '.hms-dept-row > div',
        '.hms-service-tiles > div',
        '.hms-serv',
        '.dev-list > div',
        '.hms-contact-card',
        '.hms-book',
        '.testimonial',
        '.hms-inner > .container > .row > div',
        '.footer-area .col-md-4',
        '.footer-area .col-md-6',
        '.footer-area .col-md-3',
        '.footer-area .col-md-8',
        '.footer-area .col-md-4'
    ];

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function collect() {
        var found = [];
        var seen = [];

        for (var i = 0; i < SELECTORS.length; i++) {
            var matches = document.querySelectorAll(SELECTORS[i]);

            for (var j = 0; j < matches.length; j++) {
                var node = matches[j];

                if (seen.indexOf(node) !== -1) {
                    continue;
                }

                seen.push(node);
                found.push(node);
            }
        }

        // Only the outermost match moves: a card inside a moving row must not
        // travel twice.
        var nodes = [];

        for (var k = 0; k < found.length; k++) {
            var parent = found[k].parentElement;
            var nested = false;

            while (parent && parent !== document.body) {
                if (seen.indexOf(parent) !== -1) {
                    nested = true;
                    break;
                }

                parent = parent.parentElement;
            }

            if (!nested) {
                nodes.push(found[k]);
            }
        }

        return nodes;
    }

    function stagger(nodes) {
        var groups = {};

        for (var i = 0; i < nodes.length; i++) {
            var node = nodes[i];
            var parent = node.parentNode;
            var key = parent ? (parent.getAttribute('data-reveal-group') || 'g' + i) : 'g' + i;
            var index = groups[key] || 0;

            groups[key] = index + 1;
            node.style.setProperty('--reveal-delay', Math.min(index, 6) * 70 + 'ms');
        }
    }

    function show(node) {
        node.classList.add('is-visible');
        node.classList.remove('is-above');
    }

    function hideAbove(node) {
        node.classList.remove('is-visible');
        node.classList.add('is-above');
    }

    function hideBelow(node) {
        node.classList.remove('is-visible');
        node.classList.remove('is-above');
    }

    function init() {
        var nodes = collect();

        if (!nodes.length) {
            return;
        }

        stagger(nodes);

        for (var i = 0; i < nodes.length; i++) {
            nodes[i].classList.add('hms-reveal');
        }

        if (reduceMotion || !('IntersectionObserver' in window)) {
            for (var k = 0; k < nodes.length; k++) {
                show(nodes[k]);
            }

            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            for (var e = 0; e < entries.length; e++) {
                var entry = entries[e];
                var node = entry.target;

                if (entry.isIntersecting) {
                    show(node);
                } else if (entry.boundingClientRect.top < 0) {
                    hideAbove(node);
                } else {
                    hideBelow(node);
                }
            }
        }, {
            rootMargin: '0px 0px -8% 0px',
            threshold: 0.12
        });

        for (var m = 0; m < nodes.length; m++) {
            observer.observe(nodes[m]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
