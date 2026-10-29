    <footer class="app-footer no-print">
      <div>ProjectHub เวอร์ชัน <?= APP_VERSION ?> · ระบบบริหารจัดการรายวิชาโครงงาน · <?= e(college_name()) ?></div>
      <div><i class="bi bi-code-slash"></i> <?= e(DEVELOPER_CREDIT) ?></div>
    </footer>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('assets/app.js') ?>"></script>
<?php if (!empty($pageScripts)) echo $pageScripts; ?>
</body>
</html>
