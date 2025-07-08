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
                                            <h3>Treasury &amp; Risk Management Systems</h3>
                                            <p>North America</p>
                                        </div>
                                        <img class="rt-explore-image"
                                             src="https://realtreasury.com/wp-content/uploads/2025/07/treasury-tech-market-clean-06-2025.png"
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
                            <div class="rt-services-menu">
                                <div class="rt-services-grid">
                                    <div class="rt-service-item">
                                        <div class="rt-service-title">Treasury Tech Portal</div>
                                        <div class="rt-service-desc">Access curated treasury tech stack demos and solution overviews.</div>
                                        <a href="https://realtreasury.com/treasury-tech-portal/" class="rt-service-cta tpa-btn-ready">Request Access</a>
                                    </div>
                                    <div class="rt-service-item">
                                        <div class="rt-service-title">Treasury Insights Workshop</div>
                                        <div class="rt-service-desc">25-minute deep dive sharing real-world insights.</div>
                                        <a href="https://us06web.zoom.us/meeting/register/fnF_UW-WT-SLztDpcVWU6Q#/registration" target="_blank" class="rt-service-cta">Join Workshop</a>
                                    </div>
                                    <div class="rt-service-item">
                                        <div class="rt-service-title">Technology Selection</div>
                                        <div class="rt-service-desc">Expert-guided evaluation to find your ideal treasury platform.</div>
                                        <a href="https://outlook.office.com/book/RealTreasuryMeeting@realtreasury.com/s/LgF7vpFIP0qANup2hPHi_g2?ismsaljsauthenabled" target="_blank" class="rt-service-cta">Start Assessment</a>
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
                            <div class="rt-about-menu">
                                <div class="rt-main-menu-links">
                                    <a href="https://realtreasury.com/about/" class="rt-main-menu-link">About Us</a>
                                    <a href="https://realtreasury.com/team/" class="rt-main-menu-link">Team</a>
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
