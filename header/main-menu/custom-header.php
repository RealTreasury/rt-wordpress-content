add_action( 'astra_body_top', 'add_my_custom_header_html' );
function add_my_custom_header_html() {
    ?>
    <!-- Paste the HTML from the document here -->
    <nav class="rt-nav-container">
        <div class="rt-nav-wrapper">
            <!-- Logo -->
            <div class="rt-logo">
                <a href="https://realtreasury.com/">
                    <img src="https://realtreasury.com/wp-content/uploads/2025/06/White-logo-no-background.png"
                         alt="Real Treasury" 
                         onerror="this.onerror=null;this.src='https://placehold.co/200x50/ffffff/7216f4?text=Real+Treasury';">
                </a>
            </div>

            <!-- Mobile Toggle -->
            <button class="rt-mobile-toggle">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <!-- Navigation Menu -->
            <div class="rt-nav" id="mainNav">
                <!-- Insights -->
                <div class="rt-nav-item">
                    <a href="#" class="rt-nav-link has-dropdown">
                        INSIGHTS
                    </a>
                    <div class="rt-dropdown">
                        <div class="rt-dropdown-inner">
                            <div class="rt-main-menu">
                                <div class="rt-main-menu-column">
                                    <a href="https://realtreasury.com/treasury-tech-market/" class="rt-explore-link">
                                        <div class="chart-title-overlay">
                                            <h3>Treasury Systems</h3>
                                            <p>North America</p>
                                        </div>
                                        <img class="rt-explore-image"
                                            src="https://realtreasury.com/wp-content/uploads/2025/10/treasury-tech-market-10-2025-clean.webp"
                                             alt="Treasury Tech Market"
                                             loading="lazy">
                                    </a>
                                </div>
                                <div class="rt-main-menu-column rt-topics-column">
                                    <h3>Topics</h3>
                                    <div class="rt-main-menu-links">
                                        <a href="https://realtreasury.com/treasury-tech-portal/" class="rt-main-menu-link tpa-btn-ready">Treasury Tech Portal</a>
                                        <a href="https://realtreasury.com/errnot/" class="rt-main-menu-link">ERR NOT Method</a>
                                        <a href="https://realtreasury.com/treasury-tech-market/" class="rt-main-menu-link">Treasury Tech Market</a>
                                        <a href="https://realtreasury.com/posts/" class="rt-main-menu-link">All Insights</a>
                                    </div>
                                </div>
                                <div class="rt-main-menu-column">
                                    <div class="rt-insights-widget" id="latestInsightsWidget">
                                        <div class="rt-insights-header">
                                            <h3>Latest Insights</h3>
                                        </div>
                                        <div class="rt-insights-content-area">
                                            <div class="rt-insights-track">
                                                 <div class="rt-insight-card">
                                                    <div class="rt-insight-title">The Future of Treasury Management</div>
                                                    <div class="rt-insight-excerpt">Discover the key trends and technologies shaping the future of corporate treasury.</div>
                                                    <div class="rt-insight-footer">
                                                        <span class="rt-insight-date">JUL 01, 2025</span>
                                                        <a href="#" class="rt-insight-link">Read More</a>
                                                    </div>
                                                </div>
                                                 <div class="rt-insight-card">
                                                    <div class="rt-insight-title">Optimizing Your Tech Stack</div>
                                                    <div class="rt-insight-excerpt">A practical guide to evaluating and improving your current treasury technology.</div>
                                                    <div class="rt-insight-footer">
                                                        <span class="rt-insight-date">JUN 25, 2025</span>
                                                        <a href="#" class="rt-insight-link">Read More</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="rt-insights-navigation">
                                            <div class="rt-nav-dot active"></div>
                                            <div class="rt-nav-dot"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Services -->
                <div class="rt-nav-item">
                    <a href="#" class="rt-nav-link has-dropdown">
                        SERVICES
                    </a>
                    <div class="rt-dropdown">
                        <div class="rt-dropdown-inner">
                            <div class="rt-services-enhanced">
                                <!-- Process Journey (Left Column) -->
                                <div class="rt-services-journey">
                                    <h3>🗺️ Your Journey</h3>
                                    <div class="rt-journey-steps">
                                        <a href="https://realtreasury.com/treasury-tech-portal/" class="rt-journey-step tpa-btn-ready">
                                            <div class="rt-step-number">1</div>
                                            <div class="rt-step-text">Explore tech portal</div>
                                        </a>
                                        <a href="https://realtreasury.com/tech-selection-workshop/" class="rt-journey-step">
                                            <div class="rt-step-number">2</div>
                                            <div class="rt-step-text">Join insights workshop</div>
                                        </a>
                                        <a href="https://outlook.office.com/book/RealTreasuryMeeting@realtreasury.com/s/LgF7vpFIP0qANup2hPHi_g2?ismsaljsauthenabled" class="rt-journey-step" target="_blank">
                                            <div class="rt-step-number">3</div>
                                            <div class="rt-step-text">Get expert guidance</div>
                                        </a>
                                        <a href="https://realtreasury.com/errnot/" class="rt-journey-step">
                                            <div class="rt-step-number">4</div>
                                            <div class="rt-step-text">Select ideal platform</div>
                                        </a>
                                    </div>
                                </div>

                                <!-- Services Main (Center Column) -->
                                <div class="rt-services-main">
                                    <div class="rt-services-header">
                                        <h3>Treasury Technology Selection</h3>
                                        <div class="rt-services-subtitle">Independent guidance for smarter technology decisions</div>
                                    </div>

                                    <div class="rt-services-grid">
                                        <a href="https://realtreasury.com/treasury-tech-portal/" class="rt-service-item tpa-btn-ready">
                                            <div class="rt-service-header">
                                                <div class="rt-service-icon">🔍</div>
                                                <div class="rt-service-title">Treasury Tech Portal</div>
                                            </div>
                                            <div class="rt-service-desc">Access curated treasury tech stack demos and solution overviews from 100+ evaluated vendors.</div>
                                            <span class="rt-service-cta">Request Access →</span>
                                        </a>

                                        <a href="https://realtreasury.com/tech-selection-workshop/" class="rt-service-item">
                                            <div class="rt-service-header">
                                                <div class="rt-service-icon">💡</div>
                                                <div class="rt-service-title">Treasury Insights Workshop</div>
                                            </div>
                                            <div class="rt-service-desc">25-minute deep dive sharing real-world insights and best practices from industry experts.</div>
                                            <span class="rt-service-cta">Join Workshop →</span>
                                        </a>

                                        <a href="https://outlook.office.com/book/RealTreasuryMeeting@realtreasury.com/s/LgF7vpFIP0qANup2hPHi_g2?ismsaljsauthenabled" target="_blank" class="rt-service-item">
                                            <div class="rt-service-header">
                                                <div class="rt-service-icon">🎯</div>
                                                <div class="rt-service-title">Technology Selection</div>
                                            </div>
                                            <div class="rt-service-desc">Expert-guided evaluation to find your ideal treasury platform in 4-6 weeks, not months.</div>
                                            <span class="rt-service-cta">Start Assessment →</span>
                                        </a>
                                    </div>
                                </div>

                                <!-- Testimonials (Right Column) -->
                                <div class="rt-services-stats">
                                    <h3>✨ Proven Results</h3>
                                    <div class="rt-services-testimonials">
                                        <div class="rt-services-testimonial">
                                            <div class="rt-services-testimonial-text">They brought strategic insights we hadn't considered—highlighting key features, gaps, and opportunities.</div>
                                            <div class="rt-services-testimonial-author">Thi Pham</div>
                                            <div class="rt-services-testimonial-title">CFO, Vie Management</div>
                                        </div>

                                        <div class="rt-services-testimonial">
                                            <div class="rt-services-testimonial-text">A thoughtful, structured process for selecting technology that actually fits our needs and budget.</div>
                                            <div class="rt-services-testimonial-author">Tony Vu</div>
                                            <div class="rt-services-testimonial-title">Treasurer, Broward Health</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- About -->
                <div class="rt-nav-item">
                    <a href="#" class="rt-nav-link has-dropdown">
                        ABOUT
                    </a>
                    <div class="rt-dropdown">
                        <div class="rt-dropdown-inner">
                            <div class="rt-about-enhanced">
                                <!-- Company Story -->
                                <div class="rt-about-story">
                                    <h3>OUR MISSION</h3>
                                    <p>Independent. Unbiased. Built for Treasury Teams.</p>
                                    <div class="rt-about-description">
                                        <p>We help finance leaders select treasury technology faster—with confidence, clarity, and zero vendor bias.</p>
                                        <p>Founded by former enterprise treasury practitioners and vendor veterans who grew tired of the traditional consulting model, we make technology selection easy for practitioners.</p>
                                    </div>
                                    <div class="rt-about-links">
                                        <a href="https://realtreasury.com/about/" class="rt-about-link">Our Story</a>
                                        <a href="https://realtreasury.com/errnot/" class="rt-about-link">Our Method</a>
                                    </div>
                                </div>

                                <!-- Team Preview -->
                                <div class="rt-about-team">
                                    <h3>Meet the Founders</h3>
                                    <div class="rt-founders-grid">
                                        <div class="rt-founder-card">
                                            <div class="rt-founder-image">
                                                <img src="https://realtreasury.com/wp-content/uploads/2025/08/Tim-Schultz-website-headshot.webp"
                                                     alt="Tim Schultz" loading="lazy">
                                            </div>
                                            <div class="rt-founder-info">
                                                <div class="rt-founder-name">Tim</div>
                                                <div class="rt-founder-role">Co-Founder</div>
                                            </div>
                                        </div>
                                        <div class="rt-founder-card">
                                            <div class="rt-founder-image">
                                                <img src="https://realtreasury.com/wp-content/uploads/2025/08/TraceyHeadshot-3.webp"
                                                     alt="Tracey Knight" loading="lazy">
                                            </div>
                                            <div class="rt-founder-info">
                                                <div class="rt-founder-name">Tracey</div>
                                                <div class="rt-founder-role">Co-Founder</div>
                                            </div>
                                        </div>
                                    </div>
                                    <a href="https://realtreasury.com/team/" class="rt-team-cta">Meet the Team</a>
                                </div>

                                <!-- Key Stats -->
                                <div class="rt-about-stats">
                                    <h3>By the Numbers</h3>
                                    <div class="rt-stats-grid">
                                        <div class="rt-stat-item">
                                            <div class="rt-stat-number">45+</div>
                                            <div class="rt-stat-label">Years Combined Experience</div>
                                        </div>
                                        <div class="rt-stat-item">
                                            <div class="rt-stat-number">100+</div>
                                            <div class="rt-stat-label">Vendors Evaluated</div>
                                        </div>
                                        <div class="rt-stat-item">
                                            <div class="rt-stat-number">100%</div>
                                            <div class="rt-stat-label">Independent & Unbiased</div>
                                        </div>
                                        <div class="rt-stat-item">
                                            <div class="rt-stat-number">4-6</div>
                                            <div class="rt-stat-label">Weeks to Selection</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- View Portal -->
                <div class="rt-nav-item">
                    <a href="https://realtreasury.com/treasury-tech-portal/" class="rt-cta-button tpa-btn-ready" id="portalAccessBtn">VIEW PORTAL</a>
                </div>
            </div>
        </div>
    </nav>
    <?php
}
