<?php
session_start();
$title = "Smart End-to-End Logistics";
include("layout/layout.php");
?>

<style>

.nv-hero {
    background: var(--nv-ink);
    min-height: calc(100vh - 72px);
    display: flex;
    align-items: center;
    position: relative;
    overflow: hidden;
    padding: 80px 0;
}

.nv-hero-bg {
    position: absolute;
    inset: 0;
    background-image: url('assets/images/ninjavan_hero_1777887143031.png');
    background-size: cover;
    background-position: center;
}
.nv-hero-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to right, rgba(13,13,13,1) 0%, rgba(13,13,13,0.8) 40%, rgba(13,13,13,0.2) 100%);
}

.nv-hero-grid {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
    background-size: 40px 40px;
}

.nv-hero h1 {
    font-size: clamp(36px, 5vw, 62px);
    font-weight: 800;
    color: #fff;
    line-height: 1.05;
    letter-spacing: -0.03em;
}

.nv-hero h1 .accent { color: var(--nv-red); }

.nv-hero p.lead {
    color: rgba(255,255,255,0.72);
    font-size: 18px;
    line-height: 1.7;
    max-width: 520px;
}

/* Service cards on hero */
.hero-service-card {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: var(--radius-md);
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: var(--transition);
    cursor: pointer;
}

.hero-service-card:hover {
    background: rgba(255,255,255,0.1);
    border-color: rgba(232,0,45,0.5);
    transform: translateX(4px);
}

.hero-service-icon {
    width: 52px;
    height: 52px;
    background: rgba(232,0,45,0.15);
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--nv-red);
    font-size: 22px;
    flex-shrink: 0;
    transition: var(--transition);
}

.hero-service-card:hover .hero-service-icon {
    background: var(--nv-red);
    color: #fff;
}

.hero-service-card h5 {
    color: #fff;
    font-size: 15px;
    font-weight: 600;
    margin: 0 0 4px;
}

.hero-service-card p {
    color: rgba(255,255,255,0.55);
    font-size: 13px;
    margin: 0;
    line-height: 1.5;
}

/* Track bar */
.track-bar {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 50px;
    padding: 8px 8px 8px 20px;
    display: flex;
    align-items: center;
    gap: 8px;
    max-width: 480px;
    margin-top: 32px;
}

.track-bar input {
    background: transparent;
    border: none;
    outline: none;
    color: #fff;
    font-size: 14px;
    flex: 1;
}

.track-bar input::placeholder { color: rgba(255,255,255,0.4); }

/* ===========================
   STATS SECTION
=========================== */
.nv-stats {
    padding: 56px 0;
    border-bottom: 1px solid var(--nv-border);
}

.stat-item {
    text-align: center;
    padding: 0 24px;
}

.stat-value {
    font-family: 'Poppins', sans-serif;
    font-size: clamp(32px, 4vw, 48px);
    font-weight: 800;
    color: var(--nv-red);
    letter-spacing: -0.03em;
    line-height: 1;
}

.stat-label {
    color: var(--nv-muted);
    font-size: 14px;
    margin-top: 6px;
}

.stat-divider {
    border-right: 1px solid var(--nv-border);
}

.nv-why {
    background: #fafafa;
    padding: 96px 0;
}

.section-tag {
    display: inline-block;
    background: rgba(232,0,45,0.08);
    color: var(--nv-red);
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    padding: 4px 14px;
    border-radius: 50px;
    margin-bottom: 16px;
}

.feature-card {
    background: #fff;
    border: 1px solid var(--nv-border);
    border-radius: var(--radius-lg);
    padding: 32px 28px;
    box-shadow: var(--shadow-card);
    height: 100%;
    transition: var(--transition);
}

.feature-card:hover {
    box-shadow: var(--shadow-hover);
    transform: translateY(-4px);
    border-color: rgba(232,0,45,0.2);
}

.feature-icon {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, var(--nv-red), var(--nv-red-light));
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 24px;
    margin-bottom: 20px;
}

/* ===========================
   PARTNERS MARQUEE
=========================== */
.nv-partners {
    padding: 80px 0;
    overflow: hidden;
}

.partner-track {
    display: flex;
    gap: 16px;
    animation: marquee 28s linear infinite;
    width: max-content;
}

.partner-track:hover { animation-play-state: paused; }

@keyframes marquee {
    from { transform: translateX(0); }
    to   { transform: translateX(-50%); }
}

.partner-chip {
    flex-shrink: 0;
    width: 160px;
    height: 72px;
    background: #fff;
    border: 1px solid var(--nv-border);
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: var(--shadow-card);
    font-weight: 700;
    font-size: 13px;
    color: var(--nv-muted);
    transition: var(--transition);
}

.partner-chip:hover {
    border-color: var(--nv-red);
    color: var(--nv-red);
}

.marquee-fade-left {
    position: absolute; left: 0; top: 0; bottom: 0;
    width: 96px;
    background: linear-gradient(to right, #fff, transparent);
    z-index: 2;
    pointer-events: none;
}

.marquee-fade-right {
    position: absolute; right: 0; top: 0; bottom: 0;
    width: 96px;
    background: linear-gradient(to left, #fff, transparent);
    z-index: 2;
    pointer-events: none;
}

/* ===========================
   TESTIMONIALS
=========================== */
.nv-testimonials {
    background: var(--nv-ink);
    padding: 96px 0;
}

.testimonial-card {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: var(--radius-lg);
    padding: 28px;
    height: 100%;
    transition: var(--transition);
}

.testimonial-card:hover {
    border-color: rgba(232,0,45,0.4);
    background: rgba(255,255,255,0.08);
}

.testimonial-tag {
    display: inline-block;
    background: rgba(232,0,45,0.15);
    color: var(--nv-red);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    padding: 3px 10px;
    border-radius: 50px;
    margin-bottom: 14px;
}

.testimonial-quote {
    color: rgba(255,255,255,0.82);
    font-size: 14px;
    line-height: 1.75;
    margin-bottom: 20px;
    flex: 1;
}

.testimonial-author {
    border-top: 1px solid rgba(255,255,255,0.08);
    padding-top: 14px;
}

.testimonial-name { color: #fff; font-weight: 600; font-size: 14px; }
.testimonial-role { color: rgba(255,255,255,0.45); font-size: 12px; }

/* ===========================
   SOLUTIONS
=========================== */
.nv-solutions {
    padding: 96px 0;
}

.solution-card {
    background: #fff;
    border: 1px solid var(--nv-border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-card);
    transition: var(--transition);
    height: 100%;
}

.solution-card:hover {
    box-shadow: var(--shadow-hover);
    transform: translateY(-4px);
}

.solution-img {
    height: 180px;
    background: linear-gradient(135deg, var(--nv-ink) 0%, #2d0a10 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--nv-red);
    font-size: 52px;
    position: relative;
    overflow: hidden;
}

.solution-img::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at center, rgba(232,0,45,0.2) 0%, transparent 70%);
}

.solution-body { padding: 24px; }

.solution-body h5 {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 8px;
}

.solution-body p {
    color: var(--nv-muted);
    font-size: 13px;
    line-height: 1.65;
    margin-bottom: 16px;
}

.solution-link {
    color: var(--nv-red);
    font-weight: 600;
    font-size: 13px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: var(--transition);
}

.solution-link:hover { gap: 8px; color: var(--nv-red-dark); }

/* ===========================
   CTA SECTION
=========================== */
.nv-cta {
    background: linear-gradient(135deg, var(--nv-red) 0%, var(--nv-red-light) 100%);
    padding: 80px 0;
    position: relative;
    overflow: hidden;
}

.nv-cta::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.12) 1px, transparent 0);
    background-size: 24px 24px;
}

/* ===========================
   RIDER RECRUITMENT BANNER
   =========================== */
.nv-rider-banner {
    background: var(--nv-surface-2);
    border-top: 1px solid var(--nv-border);
    border-bottom: 1px solid var(--nv-border);
    padding: 60px 0;
    position: relative;
    overflow: hidden;
}
.nv-rider-card {
    background: var(--nv-surface);
    border: 1px solid var(--nv-border);
    border-radius: var(--radius-lg);
    padding: 40px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 30px;
    position: relative;
    z-index: 2;
}
.nv-rider-text {
    flex: 1;
    min-width: 300px;
}
.nv-rider-title {
    font-size: clamp(24px, 3vw, 32px);
    font-weight: 800;
    color: var(--nv-ink);
    margin-bottom: 10px;
}
.nv-rider-desc {
    color: var(--nv-muted);
    font-size: 15px;
    line-height: 1.6;
    margin: 0;
    max-width: 600px;
}
.nv-rider-action {
    flex-shrink: 0;
}
.nv-rider-badge {
    background: rgba(232,0,45,0.08);
    color: var(--nv-red);
    font-weight: 700;
    font-size: 12px;
    text-transform: uppercase;
    padding: 6px 14px;
    border-radius: 50px;
    display: inline-block;
    margin-bottom: 15px;
    letter-spacing: 0.05em;
}
</style>

<!-- ===========================
     HERO
=========================== -->
<section class="nv-hero" id="track">
    <div class="nv-hero-bg"></div>
    <div class="nv-hero-overlay"></div>
    <div class="nv-hero-grid"></div>

    <div class="container position-relative">
        <div class="row align-items-center g-5">

            <!-- LEFT: TEXT -->
            <div class="col-lg-6">
                <h1>
                    Smart End-to-End Logistics:
                    <span class="accent">Powered by Tech</span>,
                    Built for Growth
                </h1>

                <p class="lead mt-4">
                    We deliver cost-effective solutions for cargo of all sizes —
                    e-commerce parcels, direct-to-store deliveries, and corporate
                    channels across the Philippines.
                </p>

                <div class="d-flex flex-wrap gap-3 mt-4">
                    <a href="/ninjavan/auth/register.php" class="btn-nv" style="font-size:15px; padding:13px 28px;">
                        Get Started Free
                    </a>
                    <a href="#solutions" class="btn-nv-outline-light" style="font-size:15px; padding:13px 28px;">
                        Explore Solutions
                    </a>
                </div>

                <!-- TRACK BAR -->
                <form class="track-bar" action="/ninjavan/public_track.php" method="GET">
                    <i class="bi bi-search text-muted" style="color:rgba(255,255,255,0.4)!important;"></i>
                    <input type="text" name="trk" placeholder="Enter tracking number..." required>
                    <button type="submit" class="btn-nv" style="padding:9px 20px; font-size:13px; border-radius:50px; border:none;">
                        Track
                    </button>
                </form>
            </div>

            <!-- RIGHT: SERVICE CARDS -->
            <div class="col-lg-6">
                <div class="d-flex flex-column gap-3">

                    <div class="hero-service-card">
                        <div class="hero-service-icon">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <div>
                            <h5>Ninja Fulfillment</h5>
                            <p>Cost-effective warehousing and fulfillment built to scale with your business.</p>
                        </div>
                    </div>

                    <div class="hero-service-card">
                        <div class="hero-service-icon">
                            <i class="bi bi-truck"></i>
                        </div>
                        <div>
                            <h5>Ninja Restock</h5>
                            <p>Faster B2B inventory transport, keeping your stores fully stocked.</p>
                        </div>
                    </div>

                    <div class="hero-service-card">
                        <div class="hero-service-icon">
                            <i class="bi bi-send"></i>
                        </div>
                        <div>
                            <h5>Ninja Dash</h5>
                            <p>Express e-commerce parcel and corporate mail delivery, on time, every time.</p>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</section>


<!-- ===========================
     STATS
=========================== -->
<section class="nv-stats">
    <div class="container">
        <div class="row text-center">

            <div class="col-md-4 stat-divider">
                <div class="stat-value">120M+</div>
                <div class="stat-label">Southeast Asians served</div>
            </div>

            <div class="col-md-4 stat-divider">
                <div class="stat-value">2,000,000</div>
                <div class="stat-label">parcels delivered every day</div>
            </div>

            <div class="col-md-4">
                <div class="stat-value">100%</div>
                <div class="stat-label">coverage in Southeast Asia</div>
            </div>

        </div>
    </div>
</section>


<!-- ===========================
     WHY CHOOSE US
=========================== -->
<section class="nv-why">
    <div class="container">

        <div class="text-center mb-5">
            <div class="section-tag">Why Choose Us</div>
            <h2 style="font-size:clamp(28px,4vw,42px);">Reliable, Scalable Logistics<br>Powered by People & Technology</h2>
            <p class="text-muted mt-3" style="max-width:520px; margin:0 auto;">
                From small sellers to large enterprises — we have the tools and team to move your business forward.
            </p>
        </div>

        <div class="row g-4">

            <div class="col-sm-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-diagram-3"></i></div>
                    <h5 style="font-size:16px; margin-bottom:10px;">End-to-End Logistics Expertise</h5>
                    <p class="text-muted" style="font-size:13px; line-height:1.65; margin:0;">
                        Seamlessly manage logistics from storage and sorting to parcel delivery — efficient and hassle-free, every time.
                    </p>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-layers"></i></div>
                    <h5 style="font-size:16px; margin-bottom:10px;">Flexible, Scalable Services</h5>
                    <p class="text-muted" style="font-size:13px; line-height:1.65; margin:0;">
                        Adaptable solutions that support your growth, whether you're a small business or a large enterprise.
                    </p>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-cpu"></i></div>
                    <h5 style="font-size:16px; margin-bottom:10px;">Smart, Tech-Driven Solutions</h5>
                    <p class="text-muted" style="font-size:13px; line-height:1.65; margin:0;">
                        Real-time data, AI-enabled automation, and secure parcel sorting to boost speed and accuracy.
                    </p>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-headset"></i></div>
                    <h5 style="font-size:16px; margin-bottom:10px;">Dedicated Account Management</h5>
                    <p class="text-muted" style="font-size:13px; line-height:1.65; margin:0;">
                        Direct access to operational visibility with account managers tailoring logistics to your needs.
                    </p>
                </div>
            </div>

        </div>

        <div class="text-center mt-5">
            <a href="#solutions" class="btn-nv" style="font-size:15px; padding:13px 32px;">Explore Solutions</a>
        </div>

    </div>
</section>


<!-- ===========================
     PARTNERS MARQUEE
=========================== -->
<section class="nv-partners">
    <div class="container text-center mb-5">
        <div class="section-tag">Trusted Partners</div>
        <h2 style="font-size:clamp(26px,3.5vw,38px);">Trusted by Leading Brands,<br>Platforms & Institutions</h2>
        <p class="text-muted mt-3" style="max-width:560px; margin:12px auto 0;">
            From top e-commerce platforms to banks, financial institutions, and government organizations —
            we're the trusted partner for reliable logistics.
        </p>
    </div>

    <div class="position-relative">
        <div class="marquee-fade-left"></div>
        <div class="marquee-fade-right"></div>
        <div class="partner-track">
            <?php
            $partners = ["Shein","Zalora","Amazon","Robinsons","SM Retail","Uniqlo","TGP","Happy Skin","Edamama","TikTok Shop","EastWest","Metrobank","GCash","Etaily","EcoShift","DrinkAid","KJM","Nutrie"];
            // Duplicate for seamless loop
            $all = array_merge($partners, $partners);
            foreach($all as $p): ?>
                <div class="partner-chip"><?= $p ?></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>


<!-- ===========================
     TESTIMONIALS
=========================== -->
<section class="nv-testimonials">
    <div class="container">

        <div class="text-center mb-5">
            <div class="section-tag" style="background:rgba(232,0,45,0.2);">Testimonials</div>
            <h2 style="font-size:clamp(28px,4vw,42px); color:#fff;">Hear From Our Partners</h2>
            <p style="color:rgba(255,255,255,0.55); max-width:460px; margin:12px auto 0; font-size:15px;">
                Real stories from businesses we've helped grow.
            </p>
        </div>

        <div class="row g-4">

            <?php
            $testimonials = [
                ["tag"=>"Low Shipping Fees","quote"=>"I stay with them not just because the shipping fees are lower than competitors, but also because of their willingness to support my business.","name"=>"Hannah Pham","role"=>"CEO, HVVG Company"],
                ["tag"=>"Reliable Fulfillment","quote"=>"Having a reliable fulfillment partner allowed us to focus on other parts of the business as we grew. We look forward to scaling further together.","name"=>"Janine Muñez","role"=>"KJM Cosmetics"],
                ["tag"=>"Wide Coverage","quote"=>"Without their nationwide delivery service we wouldn't be able to reach as many people to experience and try our products.","name"=>"Jethro Cerezo","role"=>"CEO, Bonavita Philippines"],
                ["tag"=>"On-time Delivery","quote"=>"They ship to many areas in the Philippines — we're confident the parcels will arrive on time.","name"=>"Jane Regasa-Co","role"=>"CEO, Core Virtual Mall"],
            ];
            foreach($testimonials as $t): ?>
            <div class="col-sm-6 col-lg-3">
                <div class="testimonial-card d-flex flex-column">
                    <div style="color:var(--nv-red); font-size:28px; margin-bottom:12px;">
                        <i class="bi bi-quote"></i>
                    </div>
                    <div class="testimonial-tag"><?= $t['tag'] ?></div>
                    <p class="testimonial-quote">"<?= $t['quote'] ?>"</p>
                    <div class="testimonial-author">
                        <div class="testimonial-name"><?= $t['name'] ?></div>
                        <div class="testimonial-role"><?= $t['role'] ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

        </div>
    </div>
</section>


<!-- ===========================
     RIDER RECRUITMENT
=========================== -->
<section class="nv-rider-banner">
    <div class="container">
        <div class="nv-rider-card">
            <div class="nv-rider-text">
                <span class="nv-rider-badge"><i class="bi bi-bicycle me-1"></i> Ride With Us</span>
                <h3 class="nv-rider-title">Want to be a Rider? Apply Now!</h3>
                <p class="nv-rider-desc">
                    Be your own boss and earn competitive rates. Join our growing fleet of delivery riders across the Philippines. Flexible hours, great rewards, and support at every step.
                </p>
            </div>
            <div class="nv-rider-action">
                <a href="/ninjavan/auth/register_rider.php" class="btn-nv" style="font-size:16px; padding:14px 32px;">
                    Apply to Ride <i class="bi bi-arrow-right ms-2"></i>
                </a>
            </div>
        </div>
    </div>
</section>


<!-- ===========================
     SOLUTIONS
=========================== -->
<section class="nv-solutions" id="solutions">
    <div class="container">

        <div class="mb-5">
            <div class="section-tag">Core Solutions</div>
            <h2 style="font-size:clamp(28px,4vw,42px);">Core Business Solutions</h2>
            <p class="text-muted mt-3" style="max-width:520px; font-size:15px;">
                A wide range of solutions to streamline and enhance your delivery operations —
                from last-mile delivery to full-scale 3PL services.
            </p>
        </div>

        <div class="row g-4">

            <?php
            $solutions = [
                ["icon"=>"bi-send", "img"=>"ninjavan_dash_1777887705477.png", "title"=>"Ninja Dash","desc"=>"Efficient last-mile delivery for e-commerce parcels and corporate documents — perfect for businesses managing 500+ parcels monthly."],
                ["icon"=>"bi-truck", "img"=>"ninjavan_rider_1777887210880.png", "title"=>"Ninja Restock","desc"=>"Flexible trucking solutions for B2B and direct-to-store replenishment, enabling frequent deliveries to stores or warehouses."],
                ["icon"=>"bi-box-seam", "img"=>"ninjavan_warehouse_1777887654232.png", "title"=>"Fulfillment & Warehousing","desc"=>"Centralized warehouse and fulfillment with an automated system for ordering, tracking, and inventory management."],
                ["icon"=>"bi-credit-card", "img"=>"ninjavan_hero_1777887143031.png", "title"=>"Card & Document Delivery","desc"=>"Secure, reliable card and document delivery for banks, financial institutions, and government agencies."],
            ];
            foreach($solutions as $s): ?>
            <div class="col-sm-6 col-lg-3">
                <div class="solution-card">
                    <div class="solution-img" style="background-image: url('assets/images/<?= $s['img'] ?>'); background-size: cover; background-position: center;">
                        <div style="position:absolute; inset:0; background:rgba(232,0,45,0.2);"></div>
                        <i class="bi <?= $s['icon'] ?>" style="position:relative; z-index:1; color:#fff; text-shadow:0 2px 10px rgba(0,0,0,0.8);"></i>
                    </div>
                    <div class="solution-body">
                        <h5><?= $s['title'] ?></h5>
                        <p><?= $s['desc'] ?></p>
                        <a href="#" class="solution-link">
                            Learn more <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

        </div>
    </div>
</section>


<!-- ===========================
     CTA
=========================== -->
<section class="nv-cta">
    <div class="container position-relative">
        <div class="row align-items-center g-4">

            <div class="col-md-8">
                <h2 style="font-size:clamp(26px,4vw,40px); color:#fff; margin:0 0 10px;">
                    Ready to scale your logistics?
                </h2>
                <p style="color:rgba(255,255,255,0.85); font-size:16px; margin:0; max-width:520px;">
                    Reimagine your route-to-market with smart technology and a deep understanding of the local Philippines landscape.
                </p>
            </div>

            <div class="col-md-4 text-md-end">
                <div class="d-flex flex-wrap gap-3 justify-content-md-end">
                    <a href="#" class="btn-nv-outline-light" style="font-size:14px; padding:12px 24px;">
                        Download Profile
                    </a>
                    <a href="/ninjavan/auth/register.php"
                       style="background:#fff; color:var(--nv-red); border-radius:50px; padding:12px 24px; font-family:'Poppins',sans-serif; font-weight:700; font-size:14px; text-decoration:none; display:inline-flex; align-items:center; gap:6px; transition:var(--transition);"
                       onmouseover="this.style.background='#f0f0f0'" onmouseout="this.style.background='#fff'">
                        Get Started →
                    </a>
                </div>
            </div>

        </div>
    </div>
</section>

<?php include("layout/footer.php"); ?>