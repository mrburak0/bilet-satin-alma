<?php
$page_title = "Ana Sayfa";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-5">


  <style>
   
    .hero-wrap { max-width: 920px; margin-inline: auto; }

   
    .hero-title { font-size: clamp(1.5rem, 1.2rem + 2vw, 2.25rem); font-weight: 700; text-align: center; margin-bottom: 1.25rem; }

   
    .search-card {
      background: #fff;
      border-radius: 1.25rem;
      padding: 1.25rem;
      box-shadow: 0 8px 24px rgba(0,0,0,.06);
    }

    
    .search-card .form-label { font-size: 1.05rem; font-weight: 600; margin-bottom: .35rem; }
    .search-card .form-control { font-size: 1.05rem; padding-block: .7rem; }
    .search-card .btn { font-size: 1.05rem; padding-block: .8rem; border-radius: .8rem; }

   
    .search-card .list-group-item { font-size: 1.02rem; padding: .6rem .85rem; }

  
    .search-card .g-2 { --bs-gutter-x: 1rem; --bs-gutter-y: 1rem; }

   
    @media (max-width: 576px) {
      .search-card { padding: 1rem; border-radius: 1rem; }
    }
  </style>

  <section class="hero-wrap">
    <h1 class="hero-title">Uygun bileti bulun</h1>

    <div class="search-card">
      <form method="get" action="listings.php" class="row g-2 align-items-end">
        <div class="col-12 col-md-6 position-relative">
          <label class="form-label" for="from">Kalkış</label>
          <input type="text" id="from" name="from" class="form-control" placeholder="İl giriniz" autocomplete="off" required>
          <div id="fromList" class="list-group position-absolute w-100" style="z-index:1000;"></div>
        </div>

        <div class="col-12 col-md-6 position-relative">
          <label class="form-label" for="to">Varış</label>
          <input type="text" id="to" name="to" class="form-control" placeholder="İl giriniz" autocomplete="off" required>
          <div id="toList" class="list-group position-absolute w-100" style="z-index:1000;"></div>
        </div>

        <div class="col-12 col-md-8">
          <label class="form-label" for="date">Tarih (Zorunlu değil)</label>
          <input type="date" id="date" name="date" class="form-control" placeholder="gg.aa.yyyy">
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label d-none d-md-block">&nbsp;</label>
          <button class="btn btn-primary w-100">Sefer Ara</button>
        </div>
      </form>
    </div>
  </section>

  <script>
    const cities = [
      "Adana","Adıyaman","Afyonkarahisar","Ağrı","Amasya","Ankara","Antalya",
      "Artvin","Aydın","Balıkesir","Bilecik","Bingöl","Bitlis","Bolu",
      "Burdur","Bursa","Çanakkale","Çankırı","Çorum","Denizli","Diyarbakır",
      "Edirne","Elazığ","Erzincan","Erzurum","Eskişehir","Gaziantep","Giresun",
      "Gümüşhane","Hakkari","Hatay","Isparta","Mersin","İstanbul","İzmir",
      "Kars","Kastamonu","Kayseri","Kırklareli","Kırşehir","Kocaeli","Konya",
      "Kütahya","Malatya","Manisa","Kahramanmaraş","Mardin","Muğla","Muş",
      "Nevşehir","Niğde","Ordu","Rize","Sakarya","Samsun","Siirt","Sinop",
      "Sivas","Tekirdağ","Tokat","Trabzon","Tunceli","Şanlıurfa","Uşak",
      "Van","Yozgat","Zonguldak","Aksaray","Bayburt","Karaman","Kırıkkale",
      "Batman","Şırnak","Bartın","Ardahan","Iğdır","Yalova","Karabük",
      "Kilis","Osmaniye","Düzce"
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
  </script>

</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
