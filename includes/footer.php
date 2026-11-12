</main>
<footer class="page-footer text-secondary small no-print">SIMS SMP Negeri 4 Pirime · Sistem administrasi sekolah</footer>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/app.js?v=<?= e(substr(hash_file('sha256', __DIR__ . '/../assets/app.js'), 0, 12)) ?>"></script>
<?php if ($currentPage === 'biodata.php'): ?><script src="assets/address-cascade.js?v=<?= e(substr(hash_file('sha256', __DIR__ . '/../assets/address-cascade.js'), 0, 12)) ?>"></script><?php endif; ?>
</body>

</html>
