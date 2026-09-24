/* ============================================================
   GoogleTranslateToolkit — documentation site.
   One document, hash routing: #/home is the board, every other
   route is a guide page. No build step, no dependencies.
   ============================================================ */
(function () {
  "use strict";

  var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var body = document.body;
  var homewrap = document.getElementById("page-home");
  var docwrap = document.getElementById("docwrap");

  var pages = Array.prototype.slice.call(document.querySelectorAll(".page"));
  var order = pages.map(function (p) { return p.id; });          // the 15 guide ids
  var ROUTES = ["home"].concat(order);

  var tocLinks = Array.prototype.slice.call(document.querySelectorAll(".toc a[data-go]"));
  var navLinks = Array.prototype.slice.call(document.querySelectorAll(".nav nav a[data-nav]"));
  var railLinks = document.getElementById("railLinks");
  var prev = document.getElementById("pg-prev");
  var next = document.getElementById("pg-next");
  var spy = null;

  var HOME_TITLE = "GoogleTranslateToolkit — Google Translate, the Laravel way";

  function titleOf(id) {
    var el = document.getElementById(id);
    return el ? el.dataset.title : "";
  }

  /* ---------------- on this page (guide only) ---------------- */
  function buildRail(page) {
    var heads = Array.prototype.slice.call(page.querySelectorAll("h2"));
    railLinks.innerHTML = "";
    heads.forEach(function (h, i) {
      if (!h.id) h.id = page.id + "-h" + i;
      var a = document.createElement("a");
      a.href = "#" + h.id;
      a.textContent = h.textContent;
      a.addEventListener("click", function (e) {
        e.preventDefault();
        h.scrollIntoView({ block: "start" });
        history.replaceState(null, "", "#/" + page.id);
      });
      railLinks.appendChild(a);
    });

    if (spy) spy.disconnect();
    if (!("IntersectionObserver" in window) || heads.length === 0) return;

    var anchors = Array.prototype.slice.call(railLinks.children);
    spy = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        var i = heads.indexOf(e.target);
        anchors.forEach(function (a, n) { a.classList.toggle("active", n === i); });
      });
    }, { rootMargin: "-80px 0px -70% 0px", threshold: 0 });
    heads.forEach(function (h) { spy.observe(h); });
  }

  function pager(id) {
    var i = order.indexOf(id);
    var p = order[i - 1], n = order[i + 1];
    prev.classList.toggle("hidden", !p);
    next.classList.toggle("hidden", !n);
    if (p) { prev.href = "#/" + p; prev.querySelector("b").textContent = titleOf(p); }
    if (n) { next.href = "#/" + n; next.querySelector("b").textContent = titleOf(n); }
  }

  /* ---------------- router ---------------- */
  function show(id, scroll) {
    if (ROUTES.indexOf(id) === -1) id = "home";
    var home = id === "home";

    body.setAttribute("data-route", id);
    homewrap.hidden = !home;
    docwrap.hidden = home;

    pages.forEach(function (p) {
      var on = !home && p.id === id;
      p.classList.toggle("on", on);
      p.hidden = !on;
    });

    tocLinks.forEach(function (a) {
      a.setAttribute("aria-current", a.dataset.go === id ? "true" : "false");
    });

    // the top bar names five routes; every other guide page belongs to "Guide"
    var named = navLinks.some(function (a) { return a.dataset.nav === id; });
    navLinks.forEach(function (a) {
      var cur = named ? a.dataset.nav === id : a.dataset.nav === "intro";
      a.classList.toggle("cur", cur);
      if (cur) a.setAttribute("aria-current", "page");
      else a.removeAttribute("aria-current");
    });

    if (home) {
      document.title = HOME_TITLE;
      if (spy) { spy.disconnect(); spy = null; }
      railLinks.innerHTML = "";
    } else {
      var page = document.getElementById(id);
      buildRail(page);
      pager(id);
      document.title = titleOf(id) + " — GoogleTranslateToolkit";
    }

    var skip = document.querySelector(".skip");
    if (skip) skip.setAttribute("href", home ? "#page-home" : "#content");

    closeMenus();
    if (scroll) window.scrollTo({ top: 0, behavior: "auto" });
  }

  // "#/route" is a route; a bare "#anchor" is an in-page jump and is left alone.
  function routeFromHash() {
    var h = location.hash || "";
    if (h === "" || h === "#") return "home";
    var m = h.match(/^#\/([A-Za-z0-9-]*)/);
    if (!m) return null;
    var id = m[1].toLowerCase();
    return ROUTES.indexOf(id) === -1 ? "home" : id;      // 404-safe
  }

  function route(scroll) {
    var id = routeFromHash();
    if (id === null) return;                             // in-page anchor
    show(id, scroll);
  }

  window.addEventListener("hashchange", function () { route(true); });

  (function boot() {
    var id = routeFromHash();
    if (id === null) { show("home", false); return; }    // deep anchor into home
    if (location.hash && location.hash !== "#/" + id) {
      history.replaceState(null, "", "#/" + id);         // unknown hash → #/home
    }
    show(id, false);
  })();

  /* ---------------- scroll progress ---------------- */
  (function progress() {
    var bar = document.getElementById("prog");
    var ticking = false;
    function paint() {
      var h = document.documentElement.scrollHeight - window.innerHeight;
      bar.style.width = (h > 0 ? Math.min(1, window.scrollY / h) * 100 : 0) + "%";
      ticking = false;
    }
    window.addEventListener("scroll", function () {
      if (!ticking) { ticking = true; requestAnimationFrame(paint); }
    }, { passive: true });
    window.addEventListener("resize", paint);
    window.addEventListener("hashchange", paint);
    paint();
  })();

  /* ---------------- theme ---------------- */
  (function theme() {
    var root = document.documentElement;
    var btn = document.getElementById("theme");
    var stored = null;
    try { stored = localStorage.getItem("gtt-theme"); } catch (e) {}
    function set(mode) {
      root.setAttribute("data-theme", mode);
      btn.textContent = mode === "light" ? "Dark" : "Light";
      btn.setAttribute("aria-label", "Switch to the " + (mode === "light" ? "dark" : "light") + " theme");
      try { localStorage.setItem("gtt-theme", mode); } catch (e) {}
    }
    set(stored === "light" ? "light" : "dark");
    btn.addEventListener("click", function () {
      set(root.getAttribute("data-theme") === "light" ? "dark" : "light");
    });
  })();

  /* ---------------- split-flap board ---------------- */
  var PHRASES = [
    { t: "CIAO MONDO", l: "Italiano · it" },
    { t: "HOLA MUNDO", l: "Español · es" },
    { t: "BONJOUR", l: "Français · fr" },
    { t: "HALLO WELT", l: "Deutsch · de" },
    { t: "OLA MUNDO", l: "Português · pt" },
    { t: "CZESC SWIECIE", l: "Polski · pl" },
    { t: "HELLO WORLD", l: "English · en" }
  ];
  var GLYPHS = "ABCDEFGHIJKLMNOPQRSTUVWXYZ ";
  var WIDTH = 13;

  function board() {
    var row = document.getElementById("flaps");
    var label = document.getElementById("nowlang");
    var count = document.getElementById("boardcount");
    var cells = [];
    var i = 0;

    for (var c = 0; c < WIDTH; c++) {
      var el = document.createElement("div");
      el.className = "flap";
      el.textContent = " ";
      row.appendChild(el);
      cells.push(el);
    }
    count.textContent = String(PHRASES.length);

    function paint() {
      var target = (PHRASES[i].t + "                ").slice(0, WIDTH);
      label.textContent = PHRASES[i].l;
      cells.forEach(function (cell, n) {
        if (reduced) { cell.textContent = target[n]; return; }
        var steps = 3 + (n % 5), s = 0;
        var id = setInterval(function () {
          s++;
          cell.classList.add("flip");
          cell.textContent = s >= steps ? target[n] : GLYPHS[Math.floor(Math.random() * GLYPHS.length)];
          setTimeout(function () { cell.classList.remove("flip"); }, 260);
          if (s >= steps) clearInterval(id);
        }, 80 + n * 16);
      });
    }

    paint();
    setInterval(function () {
      if (body.getAttribute("data-route") !== "home") return;
      i = (i + 1) % PHRASES.length;
      paint();
    }, 3600);
  }

  /* ---------------- scroll reveal ---------------- */
  function reveal() {
    var items = Array.prototype.slice.call(document.querySelectorAll(".rise"));
    if (reduced || !("IntersectionObserver" in window)) {
      items.forEach(function (el) { el.classList.add("in"); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        var siblings = Array.prototype.slice.call(e.target.parentElement.children).filter(function (n) {
          return n.classList.contains("rise");
        });
        var delay = Math.max(0, siblings.indexOf(e.target)) * 70;
        setTimeout(function () { e.target.classList.add("in"); }, delay);
        io.unobserve(e.target);
      });
    }, { rootMargin: "0px 0px -12% 0px", threshold: .12 });
    items.forEach(function (el) { io.observe(el); });
  }

  /* ---------------- counters ---------------- */
  function counters() {
    var nodes = Array.prototype.slice.call(document.querySelectorAll("[data-count]"));
    function run(el) {
      var target = parseFloat(el.dataset.count);
      var dec = parseInt(el.dataset.dec || "0", 10);
      var prefix = el.dataset.prefix || "";
      var suffix = el.dataset.suffix || "";
      var fmt = function (v) {
        return prefix + v.toLocaleString("en-US", { minimumFractionDigits: dec, maximumFractionDigits: dec }) + suffix;
      };
      if (reduced) { el.textContent = fmt(target); return; }
      var start = performance.now(), dur = 1500;
      var tick = function (now) {
        var k = Math.min(1, (now - start) / dur);
        el.textContent = fmt(target * (1 - Math.pow(1 - k, 4)));
        if (k < 1) requestAnimationFrame(tick);
      };
      requestAnimationFrame(tick);
    }
    if (!("IntersectionObserver" in window)) { nodes.forEach(run); return; }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        run(e.target);
        io.unobserve(e.target);
      });
    }, { threshold: .4 });
    nodes.forEach(function (el) { io.observe(el); });
  }

  /* ---------------- code tabs ---------------- */
  var TITLES = { c1: "translate.php", c2: "protect.php", c3: "Message.php", c4: "WebhookTest.php" };
  function tabs() {
    var buttons = Array.prototype.slice.call(document.querySelectorAll(".tab"));
    var title = document.getElementById("codetitle");
    function select(id) {
      buttons.forEach(function (b) { b.setAttribute("aria-selected", b.dataset.code === id ? "true" : "false"); });
      ["c1", "c2", "c3", "c4"].forEach(function (c) { document.getElementById(c).hidden = c !== id; });
      title.textContent = TITLES[id];
    }
    buttons.forEach(function (b) {
      b.addEventListener("click", function () { select(b.dataset.code); });
      b.addEventListener("keydown", function (e) {
        if (e.key !== "ArrowDown" && e.key !== "ArrowUp") return;
        e.preventDefault();
        var n = (buttons.indexOf(b) + (e.key === "ArrowDown" ? 1 : buttons.length - 1)) % buttons.length;
        buttons[n].focus();
        select(buttons[n].dataset.code);
      });
    });
  }

  /* ---------------- language shelf ---------------- */
  var LANGS = [
    ["it", "Italiano"], ["fr", "Français"], ["de", "Deutsch"], ["es", "Español"],
    ["pt", "Português"], ["nl", "Nederlands"], ["pl", "Polski"], ["sv", "Svenska"],
    ["da", "Dansk"], ["fi", "Suomi"], ["no", "Norsk"], ["cs", "Čeština"],
    ["el", "Ελληνικά"], ["ru", "Русский"],
    ["uk", "Українська"], ["tr", "Türkçe"],
    ["ja", "日本語"], ["ko", "한국어"], ["zh", "中文"], ["zh-TW", "繁體中文"],
    ["hi", "हिन्दी"], ["bn", "বাংলা"], ["ta", "தமிழ்"],
    ["th", "ไทย"], ["vi", "Tiếng Việt"], ["id", "Bahasa Indonesia"],
    ["sw", "Kiswahili"], ["zu", "isiZulu"], ["ha", "Hausa"], ["am", "አማርኛ"],
    ["is", "Íslenska"], ["ga", "Gaeilge"], ["cy", "Cymraeg"], ["eu", "Euskara"],
    ["haw", "ʻŌlelo Hawaiʻi"], ["mi", "Māori"], ["la", "Latina"], ["eo", "Esperanto"]
  ];
  var RTL = [
    ["ar", "العربية"], ["he", "עברית"],
    ["fa", "فارسی"], ["ur", "اردو"],
    ["ps", "پښتو"], ["dv", "ދިވެހި"],
    ["ckb", "کوردی"], ["ug", "ئۇيغۇرچە"],
    ["iw", "עברית"]
  ];
  function shelf() {
    function chip(c, rtl) {
      return '<span class="chip' + (rtl ? " rtl" : "") + '"><b>' + c[0] + "</b> · " + c[1] + (rtl ? " ⇄" : "") + "</span>";
    }
    var a = LANGS.slice(0, 20).map(function (c) { return chip(c, false); }).join("");
    var b = LANGS.slice(20).map(function (c) { return chip(c, false); }).join("") +
            RTL.map(function (c) { return chip(c, true); }).join("");
    document.getElementById("trackA").innerHTML = a + a;
    document.getElementById("trackB").innerHTML = b + b;
  }

  /* ---------------- copy buttons ---------------- */
  function copy() {
    Array.prototype.forEach.call(document.querySelectorAll(".copy"), function (btn) {
      var label = btn.textContent;
      btn.addEventListener("click", function () {
        var text = btn.dataset.copy;
        var done = function () {
          btn.textContent = "copied";
          btn.style.color = "var(--green)";
          setTimeout(function () { btn.textContent = label; btn.style.color = ""; }, 1400);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(done, done);
        } else {
          var ta = document.createElement("textarea");
          ta.value = text;
          document.body.appendChild(ta);
          ta.select();
          try { document.execCommand("copy"); } catch (e) {}
          document.body.removeChild(ta);
          done();
        }
      });
    });
  }

  /* ---------------- departure rows: occasional re-time ---------------- */
  function deps() {
    if (reduced) return;
    var rows = Array.prototype.slice.call(document.querySelectorAll("#deps .dep"));
    setInterval(function () {
      if (body.getAttribute("data-route") !== "home") return;
      var row = rows[Math.floor(Math.random() * rows.length)];
      var ms = row.querySelector(".ms");
      var st = row.querySelector(".st");
      if (st.classList.contains("api")) return;
      ms.textContent = (0.2 + Math.random() * 0.5).toFixed(1) + " ms";
    }, 2200);
  }

  /* ============================================================
     SEARCH — an index built from the rendered page at load.
     Every heading, plus the prose that follows it.
     ============================================================ */
  var INDEX = [];

  function textUntilNextHeading(head) {
    var out = [], node = head.nextElementSibling;
    while (node && !/^H[123]$/.test(node.tagName)) {
      out.push(node.textContent || "");
      node = node.nextElementSibling;
    }
    return out.join(" ").replace(/\s+/g, " ").trim().slice(0, 700);
  }

  function buildIndex() {
    // the home page, section by section
    INDEX.push({
      title: "Every language, on the board",
      label: "Home",
      route: "home",
      anchor: null,
      text: (homewrap.querySelector(".hero .dek").textContent || "").replace(/\s+/g, " ")
    });
    Array.prototype.forEach.call(homewrap.querySelectorAll("section[id]"), function (sec) {
      var h = sec.querySelector("h2, h1");
      if (!h) return;
      INDEX.push({
        title: h.textContent.replace(/\s+/g, " ").trim(),
        label: "Home",
        route: "home",
        anchor: sec.id,
        text: (sec.textContent || "").replace(/\s+/g, " ").trim().slice(0, 1200)
      });
    });

    // the guide, page by page and heading by heading
    pages.forEach(function (page) {
      var label = page.dataset.title || page.id;
      var h1 = page.querySelector("h1");
      INDEX.push({
        title: label,
        label: label,
        route: page.id,
        anchor: null,
        text: ((h1 ? h1.textContent + " " : "") + (page.textContent || "")).replace(/\s+/g, " ").trim().slice(0, 1200)
      });
      Array.prototype.forEach.call(page.querySelectorAll("h2, h3"), function (h, i) {
        if (!h.id) h.id = page.id + "-s" + i;
        INDEX.push({
          title: h.textContent.replace(/\s+/g, " ").trim(),
          label: label,
          route: page.id,
          anchor: h.id,
          text: textUntilNextHeading(h)
        });
      });
    });

    INDEX.forEach(function (r) { r.hay = (r.title + " " + r.label + " " + r.text).toLowerCase(); });
  }

  var scrim = document.getElementById("scrim");
  var qInput = document.getElementById("q");
  var hits = document.getElementById("hits");
  var shown = [];
  var cursor = 0;
  var lastFocus = null;

  function score(row, term) {
    var t = row.title.toLowerCase();
    if (t === term) return 0;
    if (t.indexOf(term) === 0) return 1;
    if (t.indexOf(term) !== -1) return 2;
    if (row.label.toLowerCase().indexOf(term) !== -1) return 3;
    return 4;
  }

  function draw() {
    var term = qInput.value.trim().toLowerCase();
    if (!term) {
      shown = INDEX.filter(function (r) { return r.anchor === null; });
    } else {
      shown = INDEX.filter(function (r) { return r.hay.indexOf(term) !== -1; })
        .map(function (r, i) { return { r: r, s: score(r, term), i: i }; })
        .sort(function (a, b) { return a.s - b.s || a.i - b.i; })
        .map(function (x) { return x.r; })
        .slice(0, 40);
    }
    if (cursor >= shown.length) cursor = 0;

    if (!shown.length) {
      hits.innerHTML = '<li class="none">Nothing matches that.</li>';
      return;
    }
    hits.innerHTML = shown.map(function (r, i) {
      return '<li role="option" aria-selected="' + (i === cursor ? "true" : "false") + '">' +
             '<a href="#/' + r.route + '">' + esc(r.title) +
             '<span class="ph">' + esc(r.label) + "</span></a></li>";
    }).join("");
    var sel = hits.querySelector('[aria-selected="true"]');
    if (sel && sel.scrollIntoView) sel.scrollIntoView({ block: "nearest" });
  }

  function esc(s) {
    return String(s).replace(/[&<>"]/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c];
    });
  }

  function go(row) {
    closePal();
    var target = "#/" + row.route;
    if (location.hash === target) show(row.route, false);
    else location.hash = target;
    if (row.anchor) {
      var el = document.getElementById(row.anchor);
      if (el) setTimeout(function () { el.scrollIntoView({ block: "start" }); }, 30);
    } else {
      window.scrollTo({ top: 0, behavior: "auto" });
    }
  }

  function openPal() {
    lastFocus = document.activeElement;
    scrim.hidden = false;
    qInput.value = "";
    cursor = 0;
    draw();
    qInput.focus();
  }
  function closePal() {
    if (scrim.hidden) return;
    scrim.hidden = true;
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  function searchPalette() {
    buildIndex();
    document.getElementById("search").addEventListener("click", openPal);
    qInput.addEventListener("input", function () { cursor = 0; draw(); });
    scrim.addEventListener("click", function (e) { if (e.target === scrim) closePal(); });
    hits.addEventListener("click", function (e) {
      var a = e.target.closest ? e.target.closest("a") : null;
      if (!a) return;
      e.preventDefault();
      var li = a.parentElement;
      var i = Array.prototype.indexOf.call(hits.children, li);
      if (shown[i]) go(shown[i]);
    });

    document.addEventListener("keydown", function (e) {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") {
        e.preventDefault();
        scrim.hidden ? openPal() : closePal();
        return;
      }
      if (scrim.hidden) return;
      if (e.key === "Escape") { e.preventDefault(); closePal(); return; }
      if (e.key === "ArrowDown") { e.preventDefault(); if (shown.length) { cursor = (cursor + 1) % shown.length; draw(); } return; }
      if (e.key === "ArrowUp") { e.preventDefault(); if (shown.length) { cursor = (cursor - 1 + shown.length) % shown.length; draw(); } return; }
      if (e.key === "Enter" && shown[cursor]) { e.preventDefault(); go(shown[cursor]); }
    });
  }

  /* ---------------- mobile menus ---------------- */
  var topnav = document.getElementById("topnav");
  var navbtn = document.getElementById("navbtn");
  var tocbtn = document.getElementById("tocbtn");
  var toc = document.getElementById("toc");

  function closeMenus() {
    if (topnav) { topnav.classList.remove("open"); navbtn.setAttribute("aria-expanded", "false"); }
    if (toc) { toc.classList.remove("open"); tocbtn.setAttribute("aria-expanded", "false"); }
  }

  function menus() {
    navbtn.addEventListener("click", function () {
      var open = topnav.classList.toggle("open");
      navbtn.setAttribute("aria-expanded", open ? "true" : "false");
      navbtn.setAttribute("aria-label", open ? "Close the menu" : "Open the menu");
    });
    tocbtn.addEventListener("click", function () {
      var open = toc.classList.toggle("open");
      tocbtn.setAttribute("aria-expanded", open ? "true" : "false");
    });
    tocLinks.forEach(function (a) { a.addEventListener("click", closeMenus); });
    navLinks.forEach(function (a) { a.addEventListener("click", closeMenus); });
    document.addEventListener("click", function (e) {
      if (topnav.contains(e.target)) return;
      topnav.classList.remove("open");
      navbtn.setAttribute("aria-expanded", "false");
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") closeMenus();
    });
  }

  /* ---------------- keyboard: ← → between guide pages ---------------- */
  function arrows() {
    document.addEventListener("keydown", function (e) {
      if (!scrim.hidden) return;
      if (e.metaKey || e.ctrlKey || e.altKey) return;
      var tag = (e.target.tagName || "").toLowerCase();
      if (tag === "input" || tag === "textarea" || e.target.isContentEditable) return;
      var cur = body.getAttribute("data-route");
      var i = order.indexOf(cur);
      if (i === -1) return;                                  // home has no pager
      if (e.key === "ArrowRight" && order[i + 1]) location.hash = "#/" + order[i + 1];
      if (e.key === "ArrowLeft" && order[i - 1]) location.hash = "#/" + order[i - 1];
    });
  }

  board();
  reveal();
  counters();
  tabs();
  shelf();
  copy();
  deps();
  searchPalette();
  menus();
  arrows();
})();
