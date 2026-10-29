// ยืนยันก่อนกดปุ่ม/ส่งฟอร์มที่มี data-confirm
document.addEventListener('submit', function (ev) {
  var msg = ev.target.getAttribute('data-confirm');
  if (msg && !window.confirm(msg)) ev.preventDefault();
});
document.addEventListener('click', function (ev) {
  var el = ev.target.closest('a[data-confirm], button[data-confirm-click]');
  if (el && !window.confirm(el.getAttribute('data-confirm') || el.getAttribute('data-confirm-click'))) ev.preventDefault();
});

// คำนวณเกรดอัตโนมัติจากคะแนน
document.addEventListener('input', function (ev) {
  if (!ev.target.matches('[data-grade-target]')) return;
  var s = parseFloat(ev.target.value), g = '';
  if (!isNaN(s)) g = s >= 80 ? '4' : s >= 75 ? '3.5' : s >= 70 ? '3' : s >= 65 ? '2.5' : s >= 60 ? '2' : s >= 55 ? '1.5' : s >= 50 ? '1' : '0';
  var t = document.querySelector(ev.target.getAttribute('data-grade-target'));
  if (t && g) t.value = g;
});
