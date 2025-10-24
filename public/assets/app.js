
const demoState = {
  user: null,
  trips: [
    {id:1, from:"İstanbul", to:"Ankara", date:"2025-10-20", time:"09:30", duration:"6s 15d", price:350, bus:"2+1", seats:40},
    {id:2, from:"İzmir", to:"Bursa", date:"2025-10-21", time:"13:00", duration:"4s 05d", price:280, bus:"2+2", seats:46},
    {id:3, from:"Ankara", to:"Antalya", date:"2025-10-22", time:"23:45", duration:"8s 00d", price:420, bus:"2+1", seats:42}
  ],
  occupiedSeats: {1:[2,5,7,12,23], 2:[1,4,9], 3:[6,10,11,33]},
  coupons: { "KUPON10":10, "SONBAHAR20":20 }
};


function $(sel, ctx=document){ return ctx.querySelector(sel); }
function $all(sel, ctx=document){ return Array.from(ctx.querySelectorAll(sel)); }
function fmtPrice(v){ return new Intl.NumberFormat('tr-TR', {style:'currency', currency:'TRY'}).format(v); }
function saveLS(k,v){ localStorage.setItem(k, JSON.stringify(v)); }
function loadLS(k,d=null){ try{ return JSON.parse(localStorage.getItem(k)) ?? d; }catch{ return d; } }


function syncAuthUI(){
  const area = $("#authArea");
  const loginButtons = $all(".navbar .btn, .navbar .nav-link[href='login.html'], .navbar .nav-link[href='register.html']");
  if(demoState.user){
    area.classList.remove("d-none");
    $("#roleLabel").textContent = demoState.user.role || "User";
    loginButtons.forEach(el=>{ if(el.textContent.match(/Giriş|Kayıt/)) el.closest("div")?.classList.add("d-none"); });
  } else {
    area.classList.add("d-none");
    loginButtons.forEach(el=>{ if(el.textContent.match(/Giriş|Kayıt/)) el.closest("div")?.classList.remove("d-none"); });
  }
  const logoutBtn = $("#logoutBtn");
  if(logoutBtn){
    logoutBtn.onclick = ()=>{ demoState.user=null; saveLS('user',null); location.href='index.html'; };
  }
}
function initAuth(){
  const stored = loadLS('user');
  if(stored) demoState.user = stored;
  syncAuthUI();
}


function initIndex(){
  const wrap = $("#featuredTrips");
  if(!wrap) return;
  demoState.trips.forEach(t=>{
    const col = document.createElement("div");
    col.className="col-12 col-md-6 col-lg-4";
    col.innerHTML = `
      <div class="card h-100 shadow-sm">
        <div class="card-body">
          <div class="small text-muted">${t.date} • ${t.time}</div>
          <h3 class="h6 mt-1">${t.from} → ${t.to}</h3>
          <div class="d-flex justify-content-between align-items-center mt-2">
            <span>${t.duration}</span>
            <strong>${fmtPrice(t.price)}</strong>
          </div>
          <div class="d-grid mt-3">
            <a href="trip-details.html?id=${t.id}" class="btn btn-outline-primary">Detay</a>
          </div>
        </div>
      </div>`;
    wrap.appendChild(col);
  });
}


function initListings(){
  const results = $("#tripResults"); if(!results) return;
  function render(list){
    results.innerHTML = "";
    list.forEach(t=>{
      const card = document.createElement("div");
      card.className="card shadow-sm";
      card.innerHTML = `
        <div class="card-body d-md-flex align-items-center justify-content-between">
          <div>
            <div class="small text-muted">${t.date} • ${t.time} • ${t.bus}</div>
            <div class="fw-semibold">${t.from} → ${t.to} <span class="text-muted">(${t.duration})</span></div>
          </div>
          <div class="d-flex align-items-center gap-3 mt-3 mt-md-0">
            <div class="fs-5 fw-bold">${fmtPrice(t.price)}</div>
            <a class="btn btn-outline-primary" href="trip-details.html?id=${t.id}">Detay</a>
            <a class="btn btn-success" href="purchase.html?trip=${t.id}">Satın Al</a>
          </div>
        </div>`;
      results.appendChild(card);
    });
  }
  render(demoState.trips);
  $("#applyFilters")?.addEventListener("click", ()=>{
    const f = $("#filterFrom").value.trim().toLowerCase();
    const to = $("#filterTo").value.trim().toLowerCase();
    const d = $("#filterDate").value;
    const s = $("#sortSelect").value;
    let list = demoState.trips.filter(t => 
      (!f || t.from.toLowerCase().includes(f)) &&
      (!to || t.to.toLowerCase().includes(to)) &&
      (!d || t.date===d)
    );
    if(s==="price") list.sort((a,b)=>a.price-b.price); else list.sort((a,b)=> a.time.localeCompare(b.time));
    render(list);
  });
}


function initTripDetails(){
  const p = new URLSearchParams(location.search);
  const id = +p.get("id") || demoState.trips[0]?.id;
  const t = demoState.trips.find(x=>x.id===id);
  if(!t) return;
  $("#tripTitle").textContent = `${t.from} → ${t.to}`;
  $("#tripMeta").textContent = `Tarih: ${t.date} | Saat: ${t.time} | Süre: ${t.duration}`;
  $("#busInfo").textContent = t.bus;
  $("#price").textContent = fmtPrice(t.price);
  $("#tripSummary").innerHTML = `
    <div>Koltuk: ${t.seats}</div>
    <div>Tarih: ${t.date}</div>
    <div>Saat: ${t.time}</div>
    <div>Fiyat: ${fmtPrice(t.price)}</div>`;
}


function initPurchase(){
  const seatMap = $("#seatMap"); if(!seatMap) return;
  const p = new URLSearchParams(location.search);
  const id = +p.get("trip") || demoState.trips[0]?.id;
  const t = demoState.trips.find(x=>x.id===id);
  const occ = new Set(demoState.occupiedSeats[id] || []);
  let selected = null; let price = t?.price || 0; let discount = 0;

  $("#summaryTrip").textContent = t ? (t.from+" → "+t.to) : "-";
  $("#totalPrice").textContent = fmtPrice(price);


  const total = t?.seats || 40;
  for(let i=1;i<=total;i++){
    const btn = document.createElement("button");
    btn.type="button";
    btn.className = "seat btn btn-sm";
    btn.textContent = i;
    if(occ.has(i)){ btn.classList.add("occupied"); }
    btn.onclick = ()=>{
      if(btn.classList.contains("occupied")) return;
      $all(".seat.selected").forEach(s=>s.classList.remove("selected"));
      btn.classList.add("selected");
      selected = i;
      $("#summarySeat").textContent = i;
    };
    seatMap.appendChild(btn);
  }

  $("#applyCoupon")?.addEventListener("click", ()=>{
    const code = $("#couponInput").value.trim().toUpperCase();
    discount = demoState.coupons[code] || 0;
    const msg = $("#couponMsg");
    if(discount>0){ msg.textContent = `${code} uygulandı: %{discount}`.replace("{discount}", discount); }
    else { msg.textContent = "Geçersiz kupon"; }
    const newTotal = Math.max(0, price * (1 - discount/100));
    $("#totalPrice").textContent = fmtPrice(newTotal);
  });

  $("#purchaseBtn")?.addEventListener("click", ()=>{
    if(!demoState.user){ alert("Lütfen giriş yapın."); location.href="login.html"; return; }
    if(!selected){ alert("Lütfen bir koltuk seçin."); return; }
    alert("Demo satın alma tamamlandı (frontend). Backend ile entegre edilecektir.");
  
    location.href = "tickets.html";
  });
}


function initTickets(){
  const list = $("#ticketList"); if(!list) return;
  const tickets = loadLS("tickets", []);
  const user = demoState.user || {name:"Demo Kullanıcı", email:"demo@example.com", credit: 500};
  $("#profileName").textContent = user.name || "—";
  $("#profileEmail").textContent = user.email || "—";
  $("#profileCredit").textContent = fmtPrice(user.credit || 0);

  if(tickets.length===0){
    list.innerHTML = `<div class="alert alert-info">Henüz biletiniz yok.</div>`;
    return;
  }
  tickets.forEach(b=>{
    const card = document.createElement("div");
    card.className="border rounded p-3";
    const until = new Date(b.date + "T" + b.time);
    const now = new Date();
    const diffHrs = (until - now) / 36e5;
    const cancelAllowed = diffHrs >= 1;
    card.innerHTML = `
      <div class="d-flex justify-content-between flex-wrap gap-2">
        <div>
          <div class="fw-semibold">${b.from} → ${b.to}</div>
          <div class="text-muted small">${b.date} • ${b.time} • Koltuk ${b.seat}</div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <a class="btn btn-outline-secondary btn-sm disabled" href="#">PDF İndir</a>
          <button class="btn btn-outline-danger btn-sm" ${cancelAllowed?"":"disabled"}>İptal Et</button>
        </div>
      </div>`;
    list.appendChild(card);
  });

  $("#addCreditBtn")?.addEventListener("click", ()=>{
    user.credit = (user.credit||0) + 100;
    $("#profileCredit").textContent = fmtPrice(user.credit);
  });
}


function initAuthPages(){
  $("#loginBtn")?.addEventListener("click", ()=>{
    const email = $("#loginEmail").value.trim();
    const pass = $("#loginPassword").value.trim();
    if(!email || !pass) return alert("Bilgileri doldurun");
    const user = {name:"Burak", email, role:"User", credit:800};
    demoState.user = user; saveLS('user', user);
    location.href = "index.html";
  });
  $("#registerBtn")?.addEventListener("click", ()=>{
    const name = $("#regName").value.trim();
    const surname = $("#regSurname").value.trim();
    const email = $("#regEmail").value.trim();
    const pass = $("#regPassword").value.trim();
    if(!name || !surname || !email || !pass) return alert("Bilgileri doldurun");
    const user = {name: name+" "+surname, email, role:"User", credit:500};
    saveLS('user', user);
    alert("Kayıt başarılı! Giriş yapıldı.");
    demoState.user = user;
    location.href = "index.html";
  });
}


function initPanels(){

  const firmBody = $("#firmTripsBody");
  if(firmBody){
    const ownTrips = demoState.trips.map(t => ({...t, firm:"DemoFirm"}));
    firmBody.innerHTML = ownTrips.map(t => `
      <tr>
        <td>${t.id}</td><td>${t.from}</td><td>${t.to}</td>
        <td>${t.date}</td><td>${t.time}</td><td>${fmtPrice(t.price)}</td>
        <td>${t.seats}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-secondary">Düzenle</button>
          <button class="btn btn-sm btn-outline-danger">Sil</button>
        </td>
      </tr>`).join("");
  }
  const couponBody = $("#couponBody");
  if(couponBody){
    couponBody.innerHTML = Object.entries(demoState.coupons)
      .map(([code,rate]) => `<tr><td>${code}</td><td>${rate}</td><td>100</td><td>2025-12-31</td>
      <td class="text-end"><button class="btn btn-sm btn-outline-secondary">Düzenle</button>
      <button class="btn btn-sm btn-outline-danger">Sil</button></td></tr>`).join("");
  }


  const firmTBody = $("#firmBody");
  if(firmTBody){
    firmTBody.innerHTML = `
      <tr><td>1</td><td>Metro Turizm</td><td>Türkiye genelinde seferler</td><td class="text-end">
        <button class="btn btn-sm btn-outline-secondary">Düzenle</button>
        <button class="btn btn-sm btn-outline-danger">Sil</button>
      </td></tr>
      <tr><td>2</td><td>Kamil Koç</td><td>Geniş otobüs ağı</td><td class="text-end">
        <button class="btn btn-sm btn-outline-secondary">Düzenle</button>
        <button class="btn btn-sm btn-outline-danger">Sil</button>
      </td></tr>`;
  }
  const firmAdminBody = $("#firmAdminBody");
  if(firmAdminBody){
    firmAdminBody.innerHTML = `
      <tr><td>Ayşe Yılmaz</td><td>ayse@example.com</td><td>Metro Turizm</td><td class="text-end">
        <button class="btn btn-sm btn-outline-secondary">Düzenle</button>
        <button class="btn btn-sm btn-outline-danger">Sil</button>
      </td></tr>`;
  }
  const globalCouponBody = $("#globalCouponBody");
  if(globalCouponBody){
    globalCouponBody.innerHTML = `
      <tr><td>GENEL15</td><td>15</td><td>500</td><td>2025-12-31</td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-secondary">Düzenle</button>
        <button class="btn btn-sm btn-outline-danger">Sil</button>
      </td></tr>`;
  }
}


document.addEventListener("DOMContentLoaded", ()=>{
  initAuth();
  initIndex();
  initListings();
  initTripDetails();
  initPurchase();
  initTickets();
  initAuthPages();
  initPanels();
});
