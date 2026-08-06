            </div>
        </div>
    </div>

    <!-- Logout Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-dark" id="exampleModalLabel" style="font-family: 'Outfit', sans-serif;">Ready to Sign Out?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-muted small">Select "Logout" below if you are ready to end your current portal session.</div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4 gap-2">
                    <button class="btn btn-light rounded-pill px-4" type="button" data-bs-dismiss="modal">Cancel</button>
                    <a class="btn btn-primary rounded-pill px-4" href="<?php echo defined('BASE_URL') ? BASE_URL : '/crm'; ?>/logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/alerts.js"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/pagination.js"></script>
</body>
</html>
