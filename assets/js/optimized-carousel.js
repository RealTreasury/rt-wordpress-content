        class OptimizedCarouselAPI {
            constructor() {
                this.baseUrl = window.location.origin;
                this.cache = new Map();
                this.cacheExpiry = 5 * 60 * 1000; // 5 minutes
                this.requestController = new AbortController();
            }

            async fetchRecentPosts(excludeId = null, useCustomEndpoint = true) {
                try {
                    if (useCustomEndpoint) {
                        const customUrl = `${this.baseUrl}/wp-json/rt/v1/posts/recent?per_page=8&page=1`;
                        const response = await this.makeRequest(customUrl);
                        if (response && Array.isArray(response) && response.length > 0) {
                            console.log('✅ Using custom RT API endpoint');
                            return this.processCustomPosts(response, excludeId);
                        }
                    }
                    console.log('🔄 Falling back to standard WordPress API');
                    return await this.fetchStandardPosts(excludeId);
                } catch (error) {
                    console.warn('API Error, using fallback:', error.message);
                    return await this.fetchStandardPosts(excludeId);
                }
            }

            async makeRequest(url, options = {}) {
                const cacheKey = url;
                const cached = this.cache.get(cacheKey);
                if (cached && Date.now() - cached.timestamp < this.cacheExpiry) {
                    console.log('📦 Using cached data for:', url);
                    return cached.data;
                }
                this.requestController.abort();
                this.requestController = new AbortController();
                const requestOptions = {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    signal: this.requestController.signal,
                    ...options
                };
                const response = await fetch(url, requestOptions);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                const data = await response.json();
                this.cache.set(cacheKey, { data, timestamp: Date.now() });
                return data;
            }

            processCustomPosts(posts, excludeId = null) {
                return posts
                    .filter(post => post.id !== excludeId && post.featured_image_url)
                    .map(post => ({
                        id: post.id,
                        title: post.title,
                        excerpt: post.excerpt,
                        content: post.content,
                        link: post.link,
                        date: post.date,
                        featured_media: post.featured_media,
                        featured_image_url: post.featured_image_url,
                        categories: post.categories || [],
                        _embedded: post._embedded || {}
                    }));
            }

            async fetchStandardPosts(excludeId = null) {
                let url = `${this.baseUrl}/wp-json/wp/v2/posts?per_page=8&orderby=date&_embed=wp:featuredmedia,wp:term&_fields=id,title,excerpt,featured_media,date,link,_embedded`;
                if (excludeId) {
                    url += `&exclude=${excludeId}`;
                }
                const posts = await this.makeRequest(url);
                return this.processStandardPosts(posts);
            }

            processStandardPosts(posts) {
                return posts
                    .filter(post => {
                        return post.featured_media && post._embedded && post._embedded['wp:featuredmedia'] && post._embedded['wp:featuredmedia'][0];
                    })
                    .map(post => {
                        const featuredMedia = post._embedded['wp:featuredmedia'][0];
                        const sizes = featuredMedia.media_details?.sizes || {};
                        const imageUrl = sizes.medium?.source_url || sizes.large?.source_url || featuredMedia.source_url;
                        return {
                            id: post.id,
                            title: post.title,
                            excerpt: post.excerpt,
                            link: post.link,
                            date: post.date,
                            featured_media: post.featured_media,
                            featured_image_url: imageUrl,
                            categories: post._embedded['wp:term'] ? post._embedded['wp:term'][0] || [] : [],
                            _embedded: post._embedded
                        };
                    });
            }

            async loadOptimizedImage(post, container) {
                if (!post.featured_image_url || !container) return null;
                return new Promise(resolve => {
                    const img = new Image();
                    img.onload = () => {
                        img.className = 'post-image';
                        img.alt = this.stripHtml(post.title?.rendered || post.title || 'Post image');
                        img.loading = 'lazy';
                        img.decoding = 'async';
                        if (container.classList.contains('post-image-skeleton')) {
                            container.replaceWith(img);
                        }
                        resolve(img);
                    };
                    img.onerror = () => {
                        console.warn('Failed to load image:', post.featured_image_url);
                        resolve(null);
                    };
                    if (this.supportsWebP()) {
                        const webpUrl = post.featured_image_url.replace(/\.(jpg|jpeg|png)$/i, '.webp');
                        img.src = webpUrl;
                        img.onerror = () => {
                            img.onerror = () => resolve(null);
                            img.src = post.featured_image_url;
                        };
                    } else {
                        img.src = post.featured_image_url;
                    }
                });
            }

            supportsWebP() {
                const canvas = document.createElement('canvas');
                canvas.width = 1;
                canvas.height = 1;
                return canvas.toDataURL('image/webp').indexOf('data:image/webp') === 0;
            }

            async checkAPIHealth() {
                const results = { customAPI: false, standardAPI: false, errors: [] };
                try {
                    const customResponse = await fetch(`${this.baseUrl}/wp-json/rt/v1/test`);
                    results.customAPI = customResponse.ok;
                    if (!customResponse.ok) {
                        results.errors.push(`Custom API error: ${customResponse.status}`);
                    }
                } catch (error) {
                    results.errors.push(`Custom API failed: ${error.message}`);
                }
                try {
                    const standardResponse = await fetch(`${this.baseUrl}/wp-json/wp/v2/posts?per_page=1`);
                    results.standardAPI = standardResponse.ok;
                    if (!standardResponse.ok) {
                        results.errors.push(`Standard API error: ${standardResponse.status}`);
                    }
                } catch (error) {
                    results.errors.push(`Standard API failed: ${error.message}`);
                }
                return results;
            }

            stripHtml(html) {
                const temp = document.createElement('div');
                temp.innerHTML = html;
                return temp.textContent || temp.innerText || '';
            }

            cleanup() {
                this.requestController.abort();
                this.cache.clear();
            }
        }

        class EnhancedRelatedPostsCarousel {
            constructor() {
                this.api = new OptimizedCarouselAPI();
                this.currentIndex = 0;
                this.posts = [];
                this.postsPerView = this.getPostsPerView();
                this.autoRotateInterval = null;
                this.intersectionObserver = null;
                this.isLoading = false;
                this.track = document.getElementById('carouselTrack');
                this.prevBtn = document.getElementById('prevBtn');
                this.nextBtn = document.getElementById('nextBtn');
                this.dotsContainer = document.getElementById('carouselDots');
                this.init();
            }

            async init() {
                console.log('🚀 Initializing Enhanced Carousel with Optimized API...');
                const health = await this.api.checkAPIHealth();
                console.log('📊 API Health Check:', health);
                this.setupIntersectionObserver();
                await this.fetchRelatedPosts();
                this.bindEvents();
                this.handleResize();
            }

            async fetchRelatedPosts() {
                if (this.isLoading) return;
                this.isLoading = true;
                try {
                    console.log('📡 Fetching posts with optimized API...');
                    const currentPostId = this.getCurrentPostId();
                    let relatedPosts = await this.api.fetchRecentPosts(currentPostId);
                    console.log(`✅ Fetched ${relatedPosts.length} posts`);
                    if (relatedPosts.length === 0) {
                        this.showNoPostsMessage();
                        return;
                    }
                    this.posts = this.shuffleArray(relatedPosts).slice(0, 6);
                    await this.renderPostsWithSkeletons();
                    this.createDots();
                    this.updateCarousel();
                    requestAnimationFrame(() => {
                        this.loadRealContent();
                        this.updateTabSlider();
                        this.startAutoRotate();
                    });
                } catch (error) {
                    console.error('❌ Error fetching posts:', error);
                    this.showErrorMessage();
                } finally {
                    this.isLoading = false;
                }
            }

            async loadRealContent() {
                console.log('🔄 Loading real content with optimized images...');
                const fragment = document.createDocumentFragment();
                for (const [index, post] of this.posts.entries()) {
                    const card = await this.createPostCard(post, index);
                    fragment.appendChild(card);
                }
                this.track.innerHTML = '';
                this.track.appendChild(fragment);
                console.log('✅ Real content loaded');
            }

            async createPostCard(post, index) {
                const card = document.createElement('div');
                card.className = 'post-card';
                card.onclick = () => window.open(post.link, '_blank');
                let excerpt = this.generateEnhancedExcerpt(post);
                card.innerHTML = `
                    <div class="post-content">
                        <div class="post-image-skeleton" data-post-index="${index}"></div>
                        <div class="post-meta">
                            <span class="post-date">${this.formatDate(post.date)}</span>
                        </div>
                        <div class="post-content-body">
                            <h3 class="post-title">${this.escapeHtml(this.getPostTitle(post))}</h3>
                            <p class="post-excerpt">${this.escapeHtml(excerpt)}</p>
                            <button class="read-more-btn">Read More</button>
                        </div>
                    </div>`;
                const imageContainer = card.querySelector('.post-image-skeleton');
                if (this.intersectionObserver && imageContainer) {
                    this.intersectionObserver.observe(imageContainer);
                } else {
                    setTimeout(() => this.loadImageForElement(imageContainer), index * 100);
                }
                return card;
            }

            async loadImageForElement(element) {
                const index = element.getAttribute('data-post-index');
                const post = this.posts[parseInt(index)];
                if (post && post.featured_image_url) {
                    await this.api.loadOptimizedImage(post, element);
                }
            }

            generateEnhancedExcerpt(post) {
                let excerpt = '';
                if (post.excerpt?.rendered) {
                    excerpt = this.api.stripHtml(post.excerpt.rendered);
                } else if (post.excerpt) {
                    excerpt = typeof post.excerpt === 'string' ? post.excerpt : this.api.stripHtml(post.excerpt);
                }
                excerpt = this.cleanExcerpt(excerpt, this.getPostTitle(post));
                if (!excerpt || excerpt.length < 40) {
                    excerpt = this.generateFallbackExcerpt(this.getPostTitle(post));
                }
                return excerpt;
            }

            getPostTitle(post) {
                if (post.title?.rendered) return post.title.rendered;
                if (typeof post.title === 'string') return post.title;
                return 'Untitled Post';
            }

            getCurrentPostId() {
                const bodyClasses = document.body.className;
                const postIdMatch = bodyClasses.match(/postid-(\d+)/);
                return postIdMatch ? parseInt(postIdMatch[1]) : null;
            }

            shuffleArray(array) {
                const shuffled = [...array];
                for (let i = shuffled.length - 1; i > 0; i--) {
                    const j = Math.floor(Math.random() * (i + 1));
                    [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
                }
                return shuffled;
            }

            escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            cleanExcerpt(text, title) {
                if (!text) return '';
                let cleaned = text
                    .replace(/\[.*?\]/g, '')
                    .replace(/^Real Treasury\s*[-–—]\s*/i, '')
                    .replace(/\s*[-–—]\s*\d{4}.*$/, '')
                    .replace(/^[^\w]*/, '')
                    .replace(/\s+/g, ' ')
                    .trim();
                if (cleaned.length >= 30 && cleaned.length <= 250) {
                    return cleaned;
                }
                return '';
            }

            generateFallbackExcerpt(title) {
                const titleLower = (title || '').toLowerCase();
                if (titleLower.includes('report') || titleLower.includes('cre') || titleLower.includes('bank')) {
                    return 'Comprehensive analysis and insights for banking professionals, featuring key trends and strategic recommendations for navigating today\'s market.';
                } else if (titleLower.includes('podcast') || titleLower.includes('interview')) {
                    return 'Expert discussion on treasury management strategies, industry trends, and innovative solutions driving the future of financial services.';
                } else if (titleLower.includes('event') || titleLower.includes('conference')) {
                    return 'Join industry leaders to explore the latest developments in treasury technology and discover actionable insights from market experts.';
                } else if (titleLower.includes('tms') || titleLower.includes('technology') || titleLower.includes('system')) {
                    return 'Discover how modern technology solutions are transforming treasury operations and driving efficiency gains across the industry.';
                } else if (titleLower.includes('payment') || titleLower.includes('network')) {
                    return 'Explore the evolving landscape of payment systems and networks, with insights into emerging technologies and market trends.';
                } else if (titleLower.includes('data') || titleLower.includes('flow')) {
                    return 'Understanding data management and flow optimization strategies for improved treasury operations and decision-making processes.';
                } else {
                    return 'Essential insights for treasury professionals navigating the evolving landscape of financial management and technology innovation.';
                }
            }

            formatDate(dateString) {
                try {
                    const date = new Date(dateString);
                    return date.toLocaleDateString('en-US', {
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric'
                    });
                } catch (error) {
                    return 'Recent';
                }
            }

            createDots() {
                const totalPages = Math.ceil(this.posts.length / this.postsPerView);
                this.dotsContainer.innerHTML = '';
                if (totalPages <= 1) return;
                const tabsContainer = document.createElement('div');
                tabsContainer.className = 'carousel-tabs-container';
                const slider = document.createElement('div');
                slider.className = 'carousel-tab-slider';
                slider.id = 'carouselTabSlider';
                tabsContainer.appendChild(slider);
                for (let i = 0; i < totalPages; i++) {
                    const tab = document.createElement('button');
                    tab.className = `carousel-tab ${i === 0 ? 'active' : ''}`;
                    tab.setAttribute('aria-label', `Go to page ${i + 1}`);
                    tab.addEventListener('click', () => this.goToSlide(i));
                    tabsContainer.appendChild(tab);
                }
                this.dotsContainer.appendChild(tabsContainer);
                this.updateTabSlider();
            }

            bindEvents() {
                this.prevBtn.addEventListener('click', () => this.prevSlide());
                this.nextBtn.addEventListener('click', () => this.nextSlide());
                let startTime, startX, startY;
                this.track.addEventListener('touchstart', (e) => {
                    startTime = Date.now();
                    startX = e.changedTouches[0].clientX;
                    startY = e.changedTouches[0].clientY;
                    this.pauseAutoRotate();
                }, { passive: true });
                this.track.addEventListener('touchmove', (e) => {
                    const currentX = e.changedTouches[0].clientX;
                    const currentY = e.changedTouches[0].clientY;
                    const deltaX = Math.abs(currentX - startX);
                    const deltaY = Math.abs(currentY - startY);
                    if (deltaX > deltaY && deltaX > 10) {
                        e.preventDefault();
                    }
                }, { passive: false });
                this.track.addEventListener('touchend', (e) => {
                    const endTime = Date.now();
                    const endX = e.changedTouches[0].clientX;
                    this.handleSwipeWithVelocity(startTime, endTime, startX, endX);
                    setTimeout(() => this.startAutoRotate(), 1000);
                }, { passive: true });
                this.track.addEventListener('mouseenter', () => this.pauseAutoRotate());
                this.track.addEventListener('mouseleave', () => this.startAutoRotate());
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'ArrowLeft') {
                        this.prevSlide();
                    } else if (e.key === 'ArrowRight') {
                        this.nextSlide();
                    }
                });
            }

            handleSwipeWithVelocity(startTime, endTime, startX, endX) {
                const swipeThreshold = 30;
                const velocityThreshold = 0.3;
                const timeDiff = endTime - startTime;
                const distance = startX - endX;
                const velocity = Math.abs(distance) / timeDiff;
                if (Math.abs(distance) > swipeThreshold || velocity > velocityThreshold) {
                    if (distance > 0) {
                        this.nextSlide();
                    } else {
                        this.prevSlide();
                    }
                }
            }

            prevSlide() {
                const totalPages = Math.ceil(this.posts.length / this.postsPerView);
                this.currentIndex = this.currentIndex <= 0 ? totalPages - 1 : this.currentIndex - 1;
                this.updateCarousel();
            }

            nextSlide() {
                const totalPages = Math.ceil(this.posts.length / this.postsPerView);
                this.currentIndex = this.currentIndex >= totalPages - 1 ? 0 : this.currentIndex + 1;
                this.updateCarousel();
            }

            goToSlide(index) {
                this.currentIndex = index;
                this.updateCarousel();
            }

            updateCarousel() {
                if (this.posts.length === 0) return;
                const totalPages = Math.ceil(this.posts.length / this.postsPerView);
                const cardElements = this.track.children;
                if (cardElements.length === 0) return;
                const cardWidth = cardElements[0]?.offsetWidth || 350;
                const gap = parseFloat(getComputedStyle(this.track).gap) || 24;
                const offset = this.currentIndex * (cardWidth + gap) * this.postsPerView;
                this.track.style.transform = `translate3d(-${offset}px, 0, 0)`;
                this.prevBtn.disabled = this.currentIndex === 0;
                this.nextBtn.disabled = this.currentIndex >= totalPages - 1;
                this.updateTabSlider();
            }

            updateTabSlider() {
                const tabs = document.querySelectorAll('.carousel-tab');
                const slider = document.getElementById('carouselTabSlider');
                if (!slider || tabs.length === 0) return;
                tabs.forEach((tab, index) => {
                    tab.classList.toggle('active', index === this.currentIndex);
                });
                const activeTab = tabs[this.currentIndex];
                if (activeTab) {
                    const offsetLeft = activeTab.offsetLeft;
                    const tabWidth = activeTab.offsetWidth;
                    slider.style.transform = `translate3d(${offsetLeft}px, 0, 0)`;
                    slider.style.width = `${tabWidth}px`;
                }
            }

            startAutoRotate() {
                this.pauseAutoRotate();
                if (this.posts.length > this.postsPerView) {
                    this.autoRotateInterval = setInterval(() => {
                        this.nextSlide();
                    }, 12000);
                }
            }

            pauseAutoRotate() {
                if (this.autoRotateInterval) {
                    clearInterval(this.autoRotateInterval);
                    this.autoRotateInterval = null;
                }
            }

            showNoPostsMessage() {
                this.track.innerHTML = `
                    <div class="no-posts-message">
                        <h3>No related posts found</h3>
                        <p>Check back later for more content!</p>
                    </div>`;
            }

            showErrorMessage() {
                this.track.innerHTML = `
                    <div class="error-message">
                        <h3>Unable to load posts</h3>
                        <p>Please refresh the page and try again.</p>
                        <button onclick="window.location.reload()" style="margin-top: 1rem; padding: 0.5rem 1rem; background: #7216f4; color: white; border: none; border-radius: 8px; cursor: pointer; min-height: 44px;">Retry</button>
                    </div>`;
            }

            getPostsPerView() {
                if (window.innerWidth <= 360) return 1;
                if (window.innerWidth <= 480) return 1;
                if (window.innerWidth <= 768) return 2;
                if (window.innerWidth <= 1200) return 3;
                return 3;
            }

            handleResize() {
                const oldPostsPerView = this.postsPerView;
                this.postsPerView = this.getPostsPerView();
                if (oldPostsPerView !== this.postsPerView) {
                    this.updateCarousel();
                    this.createDots();
                }
            }

            setupIntersectionObserver() {
                if ('IntersectionObserver' in window) {
                    this.intersectionObserver = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                this.loadImageForElement(entry.target);
                                this.intersectionObserver.unobserve(entry.target);
                            }
                        });
                    }, {
                        rootMargin: '50px',
                        threshold: 0.1
                    });
                }
            }

            cleanup() {
                this.api.cleanup();
                this.pauseAutoRotate();
                if (this.intersectionObserver) {
                    this.intersectionObserver.disconnect();
                }
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            if ('requestIdleCallback' in window) {
                requestIdleCallback(() => new EnhancedRelatedPostsCarousel());
            } else {
                setTimeout(() => new EnhancedRelatedPostsCarousel(), 0);
            }
        });
