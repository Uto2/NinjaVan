<!-- ===========================
     FOOTER
=========================== -->
<footer class="nv-footer">
    <div class="container">
        <div class="row g-5">

            <!-- BRAND COL -->
            <div class="col-lg-4">
                <div class="nv-footer-brand">ninja<span>van</span></div>
                <p style="font-size:14px; line-height:1.7; max-width:280px;">
                    Smart end-to-end logistics in the Philippines.
                    Cost-effective fulfillment, B2B restock, and last-mile delivery powered by tech.
                </p>
                <div class="d-flex gap-3 mt-4">
                    <a href="#" style="color:rgba(255,255,255,0.5); font-size:20px; transition:color 0.2s;"
                       onmouseover="this.style.color='#e8002d'" onmouseout="this.style.color='rgba(255,255,255,0.5)'">
                        <i class="bi bi-facebook"></i>
                    </a>
                    <a href="#" style="color:rgba(255,255,255,0.5); font-size:20px; transition:color 0.2s;"
                       onmouseover="this.style.color='#e8002d'" onmouseout="this.style.color='rgba(255,255,255,0.5)'">
                        <i class="bi bi-instagram"></i>
                    </a>
                    <a href="#" style="color:rgba(255,255,255,0.5); font-size:20px; transition:color 0.2s;"
                       onmouseover="this.style.color='#e8002d'" onmouseout="this.style.color='rgba(255,255,255,0.5)'">
                        <i class="bi bi-linkedin"></i>
                    </a>
                    <a href="#" style="color:rgba(255,255,255,0.5); font-size:20px; transition:color 0.2s;"
                       onmouseover="this.style.color='#e8002d'" onmouseout="this.style.color='rgba(255,255,255,0.5)'">
                        <i class="bi bi-twitter-x"></i>
                    </a>
                </div>
            </div>

            <!-- SOLUTIONS -->
            <div class="col-6 col-lg-2">
                <h6>Solutions</h6>
                <a href="#">Ninja Dash</a>
                <a href="#">Ninja Restock</a>
                <a href="#">Fulfillment</a>
                <a href="#">Card Delivery</a>
            </div>

            <!-- COMPANY -->
            <div class="col-6 col-lg-2">
                <h6>Company</h6>
                <a href="#">About Us</a>
                <a href="#">Newsroom</a>
                <a href="#">Careers</a>
                <a href="#">Contact Us</a>
            </div>

            <!-- SUPPORT -->
            <div class="col-6 col-lg-2">
                <h6>Support</h6>
                <a href="#">Help Center</a>
                <a href="#">Track Parcel</a>
                <a href="#">Claim a Parcel</a>
                <a href="#">FAQs</a>
            </div>

            <!-- LEGAL -->
            <div class="col-6 col-lg-2">
                <h6>Legal</h6>
                <a href="#">Terms of Use</a>
                <a href="#">Privacy Policy</a>
                <a href="#">Cookie Policy</a>
            </div>

        </div>

        <!-- BOTTOM BAR -->
        <div class="nv-footer-bottom d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <span>&copy; <?= date('Y') ?> Ninja Van Philippines. All rights reserved.</span>
            <span>Made with <span style="color:var(--nv-red);">♥</span> in the Philippines</span>
        </div>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- TOAST HANDLER -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll('.auto-toast').forEach(function(el){
        new bootstrap.Toast(el, { delay: 3000 }).show();
    });
});

// Track Parcel navbar dropdown handler
function doNavTrack(){
    const input = document.getElementById('navTrackInput');
    if(!input) return;
    const num = input.value.trim();
    if(!num){ input.focus(); input.style.borderColor='var(--nv-red)'; return; }
    // Scroll to hero track bar and pre-fill it
    const heroInput = document.querySelector('.track-bar input');
    if(heroInput){
        heroInput.value = num;
        const heroSection = document.getElementById('track');
        if(heroSection) heroSection.scrollIntoView({ behavior: 'smooth' });
    } else {
        // If not on index page, redirect with query param
        window.location.href = '/ninjavan/index.php?track=' + encodeURIComponent(num) + '#track';
    }
}

// Pre-fill track bar from URL param
document.addEventListener('DOMContentLoaded', function(){
    const params = new URLSearchParams(window.location.search);
    const t = params.get('track');
    if(t){
        const heroInput = document.querySelector('.track-bar input');
        if(heroInput) heroInput.value = t;
    }
});
</script>

</body>
</html>
