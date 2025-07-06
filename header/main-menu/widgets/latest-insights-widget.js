(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var widget = document.getElementById('latestInsightsWidget');
        if (!widget) return;

        var track = widget.querySelector('.rt-insights-track');
        var navigation = widget.querySelector('.rt-insights-navigation');
        var currentIndex = 0;
        var insights = [];

        var demoInsights = [
            {
                title: 'The Future of Treasury Management',
                excerpt: 'Discover the key trends and technologies shaping the future of corporate treasury.',
                date: '2025-07-01',
                link: '#'
            },
            {
                title: 'Optimizing Your Tech Stack',
                excerpt: 'A practical guide to evaluating and improving your current treasury technology.',
                date: '2025-06-25',
                link: '#'
            }
        ];

        function loadInsights() {
            fetch('/wp-json/wp/v2/posts?per_page=4&orderby=date&_fields=title,excerpt,date,link')
                .then(function(resp) { return resp.ok ? resp.json() : Promise.reject(); })
                .then(function(posts) {
                    insights = posts.map(function(post) {
                        return {
                            title: decodeHtml(post.title && post.title.rendered ? post.title.rendered : 'Untitled'),
                            excerpt: stripHtml(post.excerpt && post.excerpt.rendered ? post.excerpt.rendered : ''),
                            date: post.date,
                            link: post.link
                        };
                    });
                    if (!insights.length) insights = demoInsights;
                })
                .catch(function() {
                    insights = demoInsights;
                })
                .finally(function() {
                    renderInsights();
                    setupNavigation();
                    startAutoRotate();
                });
        }

        function stripHtml(html) {
            var temp = document.createElement('div');
            temp.innerHTML = html;
            return (temp.textContent || temp.innerText || '').trim();
        }

        function decodeHtml(html) {
            var txt = document.createElement('textarea');
            txt.innerHTML = html;
            return txt.value;
        }

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatDate(dateString) {
            var date = new Date(dateString);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }

        function renderInsights() {
            track.innerHTML = insights.map(function(insight) {
                return '<div class="rt-insight-card" data-link="' + escapeHtml(insight.link) + '">' +
                       '<div class="rt-insight-title">' + escapeHtml(insight.title) + '</div>' +
                       '<div class="rt-insight-excerpt">' + escapeHtml(insight.excerpt) + '</div>' +
                       '<div class="rt-insight-footer">' +
                           '<span class="rt-insight-date">' + formatDate(insight.date) + '</span>' +
                           '<a href="' + escapeHtml(insight.link) + '" class="rt-insight-link">Read More</a>' +
                       '</div>' +
                    '</div>';
            }).join('');
        }

        function setupNavigation() {
            if (insights.length <= 1) {
                navigation.style.display = 'none';
                return;
            }
            navigation.innerHTML = insights.map(function(_, i) {
                return '<div class="rt-nav-dot ' + (i === 0 ? 'active' : '') + '" data-index="' + i + '"></div>';
            }).join('');
        }

        function updateDisplay() {
            track.style.transform = 'translateX(-' + (currentIndex * 100) + '%)';
            var dots = navigation.querySelectorAll('.rt-nav-dot');
            dots.forEach(function(dot, i) {
                if (i === currentIndex) {
                    dot.classList.add('active');
                } else {
                    dot.classList.remove('active');
                }
            });
        }

        function goToSlide(index) {
            currentIndex = index;
            updateDisplay();
        }

        navigation.addEventListener('click', function(e) {
            if (e.target.classList.contains('rt-nav-dot')) {
                var idx = parseInt(e.target.getAttribute('data-index'), 10);
                goToSlide(idx);
            }
        });

        track.addEventListener('click', function(e) {
            var card = e.target.closest('.rt-insight-card');
            if (card && !e.target.classList.contains('rt-insight-link')) {
                var link = card.getAttribute('data-link');
                if (link && link !== '#') {
                    window.open(link, '_blank');
                }
            }
        });

        function startAutoRotate() {
            if (insights.length > 1) {
                setInterval(function() {
                    currentIndex = (currentIndex + 1) % insights.length;
                    updateDisplay();
                }, 12000);
            }
        }

        loadInsights();
    });
})();
