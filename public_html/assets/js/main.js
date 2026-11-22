(function () {
  // Mobile menu
  var btn = document.querySelector('.menu-btn');
  var nav = document.getElementById('nav');
  if (btn && nav) {
    btn.addEventListener('click', function () {
      var open = btn.getAttribute('aria-expanded') === 'true';
      btn.setAttribute('aria-expanded', String(!open));
      nav.classList.toggle('open', !open);
    });
    nav.addEventListener('click', function (e) {
      if (e.target.closest('a')) {
        btn.setAttribute('aria-expanded', 'false');
        nav.classList.remove('open');
      }
    });
  }

  // Gallery: filter + "Show more"
  var grid = document.querySelector('.gallery');
  function pageSize() {
    var cols = grid ? getComputedStyle(grid).gridTemplateColumns.split(' ').length : 4;
    return Math.max(cols, 1) * 3; // three full rows
  }
  var PAGE = pageSize();
  var shown = PAGE;
  var current = 'all';
  var filterBtns = document.querySelectorAll('.filter button');
  var items = Array.prototype.slice.call(document.querySelectorAll('.gallery li'));
  var more = document.querySelector('.more');

  function matches(li) {
    if (current === 'all') return true;
    if (current === 'video') return li.getAttribute('data-media') === 'video';
    return li.getAttribute('data-cat') === current;
  }
  function render() {
    var n = 0, total = 0;
    items.forEach(function (li) {
      var ok = matches(li);
      if (ok) total++;
      li.hidden = !(ok && n < shown);
      if (ok && n < shown) n++;
    });
    if (more) {
      var left = total - n;
      more.hidden = left <= 0;
      more.textContent = 'Show more (' + left + ' more)';
    }
  }
  filterBtns.forEach(function (b) {
    b.addEventListener('click', function () {
      current = b.getAttribute('data-filter');
      shown = PAGE;
      filterBtns.forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); });
      render();
    });
  });
  if (more) more.addEventListener('click', function () { shown += PAGE; render(); });
  if (items.length) render();

  // Lightbox for photos and videos
  var dlg = document.querySelector('.lightbox');
  if (dlg && typeof dlg.showModal === 'function') {
    var img = dlg.querySelector('img');
    var vid = dlg.querySelector('video');
    var cap = dlg.querySelector('.lb-cap');
    document.querySelectorAll('.gallery a').forEach(function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        var isVid = a.getAttribute('data-media') === 'video';
        cap.textContent = a.getAttribute('data-caption') || '';
        img.hidden = isVid;
        vid.hidden = !isVid;
        if (isVid) {
          vid.src = a.getAttribute('href');
          dlg.showModal();
          var p = vid.play();
          if (p && p.catch) p.catch(function () {});
        } else {
          img.src = a.getAttribute('href');
          img.alt = a.getAttribute('data-caption') || 'Project photo';
          dlg.showModal();
        }
      });
    });
    dlg.addEventListener('close', function () { vid.pause(); vid.removeAttribute('src'); vid.load(); });
    dlg.querySelector('.lb-close').addEventListener('click', function () { dlg.close(); });
    dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });
  }

  // Quote form: send the same details on WhatsApp instead
  var waBtn = document.querySelector('[data-wa]');
  if (waBtn) {
    waBtn.addEventListener('click', function () {
      var f = waBtn.form;
      var v = function (n) { var el = f.elements[n]; return el ? el.value.trim() : ''; };
      var lines = ['Hello Power Plumbing, I would like a quote.'];
      if (v('service')) lines.push('Service: ' + v('service'));
      if (v('location')) lines.push('Location: ' + v('location'));
      if (v('message')) lines.push('Details: ' + v('message'));
      if (v('name')) lines.push('Name: ' + v('name'));
      if (v('phone')) lines.push('Phone: ' + v('phone'));
      window.open('https://wa.me/' + waBtn.getAttribute('data-wa') + '?text=' + encodeURIComponent(lines.join('\n')), '_blank', 'noopener');
    });
  }
})();
