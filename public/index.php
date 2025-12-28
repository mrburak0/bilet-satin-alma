<?php
$page_title = "Home Page";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-5">

  <style>
    .hero-wrap {
      max-width: 920px;
      margin-inline: auto;
    }

    .hero-title {
      font-size: clamp(1.5rem, 1.2rem + 2vw, 2.25rem);
      font-weight: 700;
      text-align: center;
      margin-bottom: 1.25rem;
    }

    .search-card {
      background: #fff;
      border-radius: 1.25rem;
      padding: 1.25rem;
      box-shadow: 0 8px 24px rgba(0, 0, 0, .06);
    }

    .search-card .form-label {
      font-size: 1.05rem;
      font-weight: 600;
      margin-bottom: .35rem;
    }

    .search-card .form-control {
      font-size: 1.05rem;
      padding-block: .7rem;
    }

    .search-card .btn {
      font-size: 1.05rem;
      padding-block: .8rem;
      border-radius: .8rem;
    }

    .search-card .list-group-item {
      font-size: 1.02rem;
      padding: .6rem .85rem;
    }

    .search-card .g-2 {
      --bs-gutter-x: 1rem;
      --bs-gutter-y: 1rem;
    }

    @media (max-width: 576px) {
      .search-card {
        padding: 1rem;
        border-radius: 1rem;
      }
    }

    .ai-section {
      background: linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%);
      padding: 2rem;
      border-radius: 1.25rem;
      margin-top: 3rem;
      border: 1px solid #e0e0e0;
      text-align: center;
      position: relative;
      overflow: hidden;
    }

    .ai-title {
      color: #4a90e2;
      font-weight: 800;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
    }

    .ai-btn {
      background: linear-gradient(90deg, #4a90e2, #007bff);
      border: none;
      color: white;
      transition: transform 0.2s;
    }

    .ai-btn:hover {
      transform: scale(1.02);
    }

    .ai-result-card {
      background: white;
      border-radius: 14px;
      padding: 14px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
      transition: transform 0.2s;
      border: 1px solid #eee;
      text-align: left;
      height: 100%;
    }

    .ai-result-card:hover {
      transform: translateY(-3px);
      border-color: #4a90e2;
    }

    .city-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 10px;
      border-radius: 999px;
      background: #f3f3f3;
      font-size: 13px;
      font-weight: 700;
    }

    .ai-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .55);
      display: none;
      align-items: center;
      justify-content: center;
      padding: 20px;
      z-index: 9999;
    }

    .ai-loader-box {
      background: #fff;
      border-radius: 18px;
      padding: 18px 18px;
      max-width: 560px;
      width: 100%;
      box-shadow: 0 10px 30px rgba(0, 0, 0, .22);
    }

    .ai-flex {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .ai-spinner {
      width: 42px;
      height: 42px;
      border: 4px solid #e5e5e5;
      border-top-color: #1f2937;
      border-radius: 50%;
      animation: spin 1s linear infinite;
      flex: 0 0 auto;
    }

    @keyframes spin {
      to {
        transform: rotate(360deg);
      }
    }

    .ai-big {
      font-size: 18px;
      font-weight: 900;
    }

    .ai-tiny {
      font-size: 13px;
      color: #666;
      margin-top: 4px;
    }

    .ai-pill {
      display: inline-block;
      margin-top: 10px;
      padding: 7px 10px;
      border-radius: 999px;
      background: #f4f4f5;
      font-size: 13px;
      font-weight: 700;
    }
  </style>

  <section class="hero-wrap">
    <h1 class="hero-title">Find suitable tickets</h1>

    <div class="search-card">
      <form method="get" action="listings.php" class="row g-2 align-items-end">
        <div class="col-12 col-md-6 position-relative">
          <label class="form-label" for="from">Departure</label>
          <input type="text" id="from" name="from" class="form-control" placeholder="Enter a city" autocomplete="off"
            required>
          <div id="fromList" class="list-group position-absolute w-100" style="z-index:1000;"></div>
        </div>

        <div class="col-12 col-md-6 position-relative">
          <label class="form-label" for="to">Destination</label>
          <input type="text" id="to" name="to" class="form-control" placeholder="Enter a city" autocomplete="off"
            required>
          <div id="toList" class="list-group position-absolute w-100" style="z-index:1000;"></div>
        </div>

        <div class="col-12 col-md-8">
          <label class="form-label" for="date">Date (Not required)</label>
          <input type="date" id="date" name="date" class="form-control" placeholder="dd.mm.yyyy">
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label d-none d-md-block">&nbsp;</label>
          <button class="btn btn-primary w-100">Search trip</button>
        </div>
      </form>
    </div>
  </section>

  <section class="hero-wrap ai-section">
    <h2 class="hero-title ai-title">✨ AI Travel Assistant</h2>
    <p class="text-muted mb-4">Think of it like, “I'm free from July 16 to December 20.” Just pick the date range, and
      I'll take care of the rest 😄</p>

    <form id="aiForm" class="row g-3 justify-content-center align-items-end">
      <div class="col-12 col-md-4">
        <label class="form-label fw-bold text-start w-100">From where?</label>
        <select name="origin" id="aiOrigin" class="form-select" required>
          <option value="" selected disabled>Select a city...</option>
          <option value="İstanbul">İstanbul</option>
          <option value="Ankara">Ankara</option>
          <option value="İzmir">İzmir</option>
          <option value="Sivas">Sivas</option>
          <option value="Antalya">Antalya</option>
        </select>
      </div>

      <div class="col-12 col-md-5">
        <label class="form-label fw-bold text-start w-100">When are you avaliable?</label>
        <div class="row g-2">
          <div class="col-6">
            <input type="date" name="start_date" id="aiStartDate" class="form-control" required>
          </div>
          <div class="col-6">
            <input type="date" name="end_date" id="aiEndDate" class="form-control" required>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-3">
        <button type="submit" id="aiBtn" class="btn btn-primary w-100 ai-btn">
          Route Recommendation🤖
        </button>
      </div>
    </form>

    <div id="aiResults" class="mt-4"></div>
  </section>

  <div class="ai-overlay" id="aiOverlay">
    <div class="ai-loader-box">
      <div class="ai-flex">
        <div class="ai-spinner"></div>
        <div>
          <div class="ai-big" id="aiLoadingTitle">Trips are being scanned…</div>
          <div class="ai-tiny" id="aiLoadingSub">The assistant is preparing the tea.☕</div>
        </div>
      </div>
      <div class="ai-pill" id="aiLoadingPill">AI is packing its bags 🧳</div>
    </div>
  </div>

  <script>
    const cities = [
      "Adana", "Adıyaman", "Afyonkarahisar", "Ağrı", "Amasya", "Ankara", "Antalya",
      "Artvin", "Aydın", "Balıkesir", "Bilecik", "Bingöl", "Bitlis", "Bolu",
      "Burdur", "Bursa", "Çanakkale", "Çankırı", "Çorum", "Denizli", "Diyarbakır",
      "Edirne", "Elazığ", "Erzincan", "Erzurum", "Eskişehir", "Gaziantep", "Giresun",
      "Gümüşhane", "Hakkari", "Hatay", "Isparta", "Mersin", "İstanbul", "İzmir",
      "Kars", "Kastamonu", "Kayseri", "Kırklareli", "Kırşehir", "Kocaeli", "Konya",
      "Kütahya", "Malatya", "Manisa", "Kahramanmaraş", "Mardin", "Muğla", "Muş",
      "Nevşehir", "Niğde", "Ordu", "Rize", "Sakarya", "Samsun", "Siirt", "Sinop",
      "Sivas", "Tekirdağ", "Tokat", "Trabzon", "Tunceli", "Şanlıurfa", "Uşak",
      "Van", "Yozgat", "Zonguldak", "Aksaray", "Bayburt", "Karaman", "Kırıkkale",
      "Batman", "Şırnak", "Bartın", "Ardahan", "Iğdır", "Yalova", "Karabük",
      "Kilis", "Osmaniye", "Düzce"
    ];

    function setupAutocomplete(inputId, listId) {
      const input = document.getElementById(inputId);
      const list = document.getElementById(listId);

      input.addEventListener('input', () => {
        const query = input.value.toLowerCase();
        list.innerHTML = '';
        if (!query) return;

        const matches = cities.filter(c => c.toLowerCase().startsWith(query)).slice(0, 8);
        matches.forEach(city => {
          const item = document.createElement('button');
          item.type = 'button';
          item.className = 'list-group-item list-group-item-action';
          item.textContent = city;
          item.onclick = () => { input.value = city; list.innerHTML = ''; };
          list.appendChild(item);
        });
      });

      document.addEventListener('click', e => {
        if (!list.contains(e.target) && e.target !== input) list.innerHTML = '';
      });

      input.addEventListener('keydown', e => {
        const items = Array.from(list.querySelectorAll('.list-group-item'));
        if (!items.length) return;
        const current = list.querySelector('.active');
        let idx = items.indexOf(current);

        if (e.key === 'ArrowDown') {
          e.preventDefault();
          idx = (idx + 1) % items.length;
          items.forEach(i => i.classList.remove('active'));
          items[idx].classList.add('active');
          items[idx].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          idx = (idx - 1 + items.length) % items.length;
          items.forEach(i => i.classList.remove('active'));
          items[idx].classList.add('active');
          items[idx].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter' && current) {
          e.preventDefault();
          input.value = current.textContent;
          list.innerHTML = '';
        }
      });
    }

    setupAutocomplete('from', 'fromList');
    setupAutocomplete('to', 'toList');

    const overlay = document.getElementById('aiOverlay');
    const loadingTitle = document.getElementById('aiLoadingTitle');
    const loadingSub = document.getElementById('aiLoadingSub');
    const loadingPill = document.getElementById('aiLoadingPill');
    let loadingTimer = null;

    const jokes = [
      ["Trips are being scanned...", "The buses say, “We turned the ignition.” 🚍", "We are looking at the map 🗺️"],
      ["AI is thinking...", "3 cities, 3 legendary plans coming in 😎", "We are drawing the route ✍️"],
      ["Adjusting the window seat...", "The flight attendants are preparing tea ☕", "The tickets are warming up 🔥"],
      ["Luggage inspection...", "Short or long sleeve? Emoji will decide 😄", "Setting the weather mode 🌤️"],
    ];

    function showLoading() {
      overlay.style.display = 'flex';
      let i = 0;
      loadingTimer = setInterval(() => {
        const pick = jokes[i % jokes.length];
        loadingTitle.textContent = pick[0];
        loadingSub.textContent = pick[1];
        loadingPill.textContent = pick[2];
        i++;
      }, 850);
    }
    function hideLoading() {
      overlay.style.display = 'none';
      if (loadingTimer) clearInterval(loadingTimer);
      loadingTimer = null;
    }

    function esc(s) {
      return String(s ?? "").replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
      }[m]));
    }

    document.getElementById('aiForm').addEventListener('submit', function (e) {
      e.preventDefault();

      const btn = document.getElementById('aiBtn');
      const resultDiv = document.getElementById('aiResults');

      const origin = document.getElementById('aiOrigin').value;
      const start_date = document.getElementById('aiStartDate').value;
      const end_date = document.getElementById('aiEndDate').value;

      if (!origin || !start_date || !end_date) {
        resultDiv.innerHTML = `<div class="alert alert-danger">Please fill in the departure city and date range.</div>`;
        return;
      }
      if (end_date < start_date) {
        resultDiv.innerHTML = `<div class="alert alert-danger">End date cannot be before the start date.</div>`;
        return;
      }

      btn.disabled = true;
      btn.innerHTML = 'Scanning...';
      showLoading();
      resultDiv.innerHTML = `<p class="text-muted">Trips are being scanned, recommendations are being cooked… 🍳</p>`;

      const formData = new FormData();
      formData.append('origin', origin);
      formData.append('start_date', start_date);
      formData.append('end_date', end_date);

      fetch('api_recommend.php', {
        method: 'POST',
        body: formData
      })
        .then(response => response.json())
        .then(data => {
          btn.disabled = false;
          btn.innerHTML = 'Route Recommendation🤖';
          hideLoading();

          if (data.error) {
            resultDiv.innerHTML = `<div class="alert alert-danger">${esc(data.error)}</div>`;
            return;
          }

          const ai = Array.isArray(data.ai) ? data.ai : [];
          const tickets = Array.isArray(data.tickets) ? data.tickets : [];

          let html = `<div class="mb-3">
              <h5 class="text-success fw-bold">
                Recommendations are ready ✅ <span class="text-muted fw-normal">(${esc(start_date)} → ${esc(end_date)})</span>
              </h5>
            </div>`;

          if (ai.length) {
            html += `<div class="row g-3">`;
            ai.forEach(item => {
              html += `
                <div class="col-12 col-md-4">
                  <div class="ai-result-card">
                    <div class="d-flex justify-content-between align-items-start">
                      <div>
                        <div class="h5 fw-bold mb-1">${esc(item.city)}</div>
                        <div class="city-pill" title="General weather">${esc(item.weather)} <span class="text-muted">General weather</span></div>
                      </div>
                    </div>
                    <hr class="my-2">
                    <div class="mb-2">📍 <b>Place to visit:</b><br><span class="text-muted">${esc(item.place)}</span></div>
                    <div>🍽️ <b>Food:</b><br><span class="text-muted">${esc(item.food)}</span></div>
                  </div>
                </div>`;
            });
            html += `</div>`;
          } else {
            html += `<div class="alert alert-warning">The AI couldn't generate a city suggestion.</div>`;
          }

          html += `<div class="mt-4">
              <h5 class="fw-bold">Found Trips</h5>
            </div>`;

          if (tickets.length) {
            html += `<div class="row g-3">`;
            tickets.forEach(t => {
              const link = `listings.php?from=${encodeURIComponent(t.departure_city)}&to=${encodeURIComponent(t.destination_city)}&date=${encodeURIComponent(t.trip_date)}`;
              html += `
                <div class="col-12 col-md-4">
                  <div class="ai-result-card h-100 d-flex flex-column justify-content-between">
                    <div>
                      <div class="h6 fw-bold mb-1">${esc(t.destination_city)}</div>
                      <small class="text-muted">${esc(t.departure_city)} departure</small>
                      <hr class="my-2">
                      <div class="mb-2">
                        📅 ${esc(t.trip_date)} <br>
                        🕒 ${esc(t.trip_time)}
                      </div>
                      <div class="text-muted small">🪑 Capacity: ${esc(t.capacity)}</div>
                    </div>
                    <div class="mt-3">
                      <div class="h4 text-primary fw-bold mb-2">${esc(t.price)} TL</div>
                      <a href="${link}" class="btn btn-sm btn-success w-100">Buy Ticket</a>
                    </div>
                  </div>
                </div>`;
            });
            html += `</div>`;
          } else {
            html += `<div class="alert alert-warning">
                The AI recommended great places, but couldn't find any trips for these routes in this date range 🙃
              </div>`;
          }

          resultDiv.innerHTML = html;
        })
        .catch(err => {
          console.error(err);
          btn.disabled = false;
          btn.innerHTML = 'Route Recommendation🤖';
          hideLoading();
          resultDiv.innerHTML = '<div class="alert alert-danger">A connection error occurred.</div>';
        });
    });
  </script>

</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>