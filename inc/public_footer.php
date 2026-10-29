<footer class="public-footer">
  <div class="container py-4 d-flex flex-wrap justify-content-between gap-2">
    <div>
      <div class="fw-semibold">งานวิจัย พัฒนานวัตกรรมและสิ่งประดิษฐ์</div>
      <div class="small opacity-75"><?= e(college_name()) ?> · ร่วมกำกับดูแลโดยฝ่ายวิชาการ และฝ่ายยุทธศาสตร์และแผนงาน</div>
    </div>
    <div class="small opacity-75 align-self-end">ProjectHub เวอร์ชัน <?= APP_VERSION ?> · © <?= date('Y') + 543 ?></div>
  </div>
  <div class="border-top border-light border-opacity-10">
    <div class="container py-2 small opacity-75"><i class="bi bi-code-slash"></i> <?= e(DEVELOPER_CREDIT) ?></div>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
