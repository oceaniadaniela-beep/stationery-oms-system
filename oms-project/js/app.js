$(function () {
    const LS = {
        users: 'oms_users',
        products: 'oms_products',
        orders: 'oms_orders',
        affiliates: 'oms_affiliates',
        currentUser: 'oms_currentUser'
    };

    function save(k, v) { localStorage.setItem(k, JSON.stringify(v)); }
    function load(k) { let v = localStorage.getItem(k); return v ? JSON.parse(v) : null; }
    function uid(p = 'id') { return p + '_' + Math.random().toString(36).slice(2, 9); }
    function toast(m, t = 2000) { $('#toast').text(m).fadeIn(150).show(); setTimeout(() => $('#toast').fadeOut(200), t); }

    // Seed admin if none
    if (!load(LS.users)) {
        save(LS.users, [{ id: uid('user'), name: 'Admin', email: 'admin@oms.local', password: 'admin123', role: 'admin' }]);
    }

    function getCurrentUser() { return load(LS.currentUser); }

    // ========== LOGIN ==========
    $('#loginForm').submit(function (e) {
        e.preventDefault();
        const email = $('#loginEmail').val().trim(), pwd = $('#loginPassword').val().trim();
        const u = (load(LS.users) || []).find(x => x.email.toLowerCase() === email.toLowerCase() && x.password === pwd);
        if (u) { save(LS.currentUser, u); showApp(); toast("Welcome " + u.name); }
        else toast("Invalid login", 3000);
    });

    $('#logoutBtn').click(() => { localStorage.removeItem(LS.currentUser); $('#app').addClass('hidden'); $('#loginScreen').removeClass('hidden'); });

    function showPage(p) { $('.page').addClass('hidden'); $('#' + p).removeClass('hidden'); }
    function showApp() { $('#loginScreen').addClass('hidden'); $('#app').removeClass('hidden'); showPage('dashboard'); $('#sidebarUser,#mobileUser').text(getCurrentUser()?.name || ''); renderAll(); }

    $('.nav-link').click(function () { showPage($(this).data('page')); });
    $('#mobileMenuBtn').click(() => $('#sidebar').toggleClass('hidden'));

    // ========== PRODUCTS ==========
    function renderProducts() {
        const arr = load(LS.products) || [];
        const tb = $('#productsTable').empty();
        arr.forEach(p => {
            tb.append(`<tr>
        <td class="p-2">${p.name}</td>
        <td class="p-2">${p.price}</td>
        <td class="p-2">
          <button class="editProductBtn" data-id="${p.id}">Edit</button>
          <button class="delProductBtn text-red-500" data-id="${p.id}">Delete</button>
        </td>
      </tr>`);
        });
    }
    $('#quickAddProduct').click(() => {
        let arr = load(LS.products) || [];
        const newProduct = {
            id: uid('prod'),
            name: 'New Product ' + (arr.length + 1),
            price: 0
        };
        arr.push(newProduct);
        save(LS.products, arr);
        renderProducts();
        toast("Product added: " + newProduct.name);
    });
    $('#addProductBtn').click(() => { $('#productModal').show(); $('#productForm')[0].reset(); $('#productId').val(''); });
    $('#closeProductModal').click(() => $('#productModal').hide());
    $('#productForm').submit(function (e) {
        e.preventDefault();
        let arr = load(LS.products) || [];
        const id = $('#productId').val();
        if (id) {
            arr = arr.map(p => p.id === id ? { ...p, name: $('#productName').val(), price: +$('#productPrice').val() } : p);
        } else {
            arr.push({ id: uid('prod'), name: $('#productName').val(), price: +$('#productPrice').val() });
        }
        save(LS.products, arr); $('#productModal').hide(); renderProducts();
    });
    $(document).on('click', '.editProductBtn', function () {
        const p = (load(LS.products) || []).find(x => x.id === $(this).data('id'));
        if (p) { $('#productId').val(p.id); $('#productName').val(p.name); $('#productPrice').val(p.price); $('#productModal').show(); }
    });
    $(document).on('click', '.delProductBtn', function () {
        let arr = load(LS.products) || []; arr = arr.filter(p => p.id != $(this).data('id')); save(LS.products, arr); renderProducts();
    });

    // ========== USERS ==========
    function renderUsers() {
        const arr = load(LS.users) || []; const tb = $('#usersTable').empty();
        arr.forEach(u => {
            tb.append(`<tr><td class="p-2">${u.name}</td><td class="p-2">${u.email}</td><td class="p-2">${u.role}</td>
      <td class="p-2"><button class="editUserBtn" data-id="${u.id}">Edit</button>
      <button class="delUserBtn text-red-500" data-id="${u.id}">Delete</button></td></tr>`);
        });
        $('#customersList').empty();
        arr.filter(u => u.role === 'customer').forEach(c => $('#customersList').append(`<option value="${c.name}">`));
    }
    $('#addUserBtn').click(() => { $('#userModal').show(); $('#userForm')[0].reset(); $('#userId').val(''); });
    $('#closeUserModal').click(() => $('#userModal').hide());
    $('#userForm').submit(function (e) {
        e.preventDefault();
        let arr = load(LS.users) || []; const id = $('#userId').val();
        if (id) {
            arr = arr.map(u => u.id === id ? { ...u, name: $('#userName').val(), email: $('#userEmail').val(), password: $('#userPassword').val(), role: $('#userRole').val() } : u);
        } else {
            arr.push({ id: uid('user'), name: $('#userName').val(), email: $('#userEmail').val(), password: $('#userPassword').val(), role: $('#userRole').val() });
        }
        save(LS.users, arr); $('#userModal').hide(); renderUsers();
    });
    $(document).on('click', '.editUserBtn', function () {
        const u = (load(LS.users) || []).find(x => x.id === $(this).data('id'));
        if (u) { $('#userId').val(u.id); $('#userName').val(u.name); $('#userEmail').val(u.email); $('#userPassword').val(u.password); $('#userRole').val(u.role); $('#userModal').show(); }
    });
    $(document).on('click', '.delUserBtn', function () { let arr = load(LS.users) || []; arr = arr.filter(u => u.id != $(this).data('id')); save(LS.users, arr); renderUsers(); });

    // ========== AFFILIATES ==========
    function renderAffiliates() {
        const arr = load(LS.affiliates) || []; const tb = $('#affiliatesTable').empty();
        arr.forEach(a => {
            tb.append(`<tr><td class="p-2">${a.name}</td><td class="p-2">${a.code}</td>
      <td class="p-2"><button class="editAffiliateBtn" data-id="${a.id}">Edit</button>
      <button class="delAffiliateBtn text-red-500" data-id="${a.id}">Delete</button></td></tr>`);
        });
    }
    $('#addAffiliateBtn').click(() => { $('#affiliateModal').show(); $('#affiliateForm')[0].reset(); $('#affiliateId').val(''); });
    $('#closeAffiliateModal').click(() => $('#affiliateModal').hide());
    $('#affiliateForm').submit(function (e) {
        e.preventDefault(); let arr = load(LS.affiliates) || []; const id = $('#affiliateId').val();
        if (id) { arr = arr.map(a => a.id === id ? { ...a, name: $('#affiliateName').val(), code: $('#affiliateCode').val() } : a); }
        else { arr.push({ id: uid('aff'), name: $('#affiliateName').val(), code: $('#affiliateCode').val() }); }
        save(LS.affiliates, arr); $('#affiliateModal').hide(); renderAffiliates();
    });
    $(document).on('click', '.editAffiliateBtn', function () {
        const a = (load(LS.affiliates) || []).find(x => x.id === $(this).data('id'));
        if (a) { $('#affiliateId').val(a.id); $('#affiliateName').val(a.name); $('#affiliateCode').val(a.code); $('#affiliateModal').show(); }
    });
    $(document).on('click', '.delAffiliateBtn', function () { let arr = load(LS.affiliates) || []; arr = arr.filter(a => a.id != $(this).data('id')); save(LS.affiliates, arr); renderAffiliates(); });

    // ========== ORDERS ==========
    function renderOrders() {
        const arr = load(LS.orders) || []; const tb = $('#ordersTable').empty();
        arr.forEach(o => {
            tb.append(`<tr><td class="p-2">${o.customer}</td><td class="p-2">${o.items}</td><td class="p-2">${o.total}</td>
      <td class="p-2"><button class="editOrderBtn" data-id="${o.id}">Edit</button>
      <button class="delOrderBtn text-red-500" data-id="${o.id}">Delete</button></td></tr>`);
        });
    }
    $('#addOrderBtn').click(() => { $('#orderModal').show(); $('#orderForm')[0].reset(); $('#orderId').val(''); });
    $('#closeOrderModal').click(() => $('#orderModal').hide());
    $('#orderForm').submit(function (e) {
        e.preventDefault(); let arr = load(LS.orders) || []; const id = $('#orderId').val();
        let cust = $('#orderCustomerInput').val().trim(); let users = load(LS.users) || [];
        let existing = users.find(u => u.name.toLowerCase() === cust.toLowerCase());
        if (!existing && cust) { const newU = { id: uid('user'), name: cust, email: cust.replace(/\s+/g, '').toLowerCase() + "@guest.local", password: "guest123", role: "customer" }; users.push(newU); save(LS.users, users); }
        if (id) { arr = arr.map(o => o.id === id ? { ...o, customer: cust, items: $('#orderItems').val(), total: +$('#orderTotal').val() } : o); }
        else { arr.push({ id: uid('ord'), customer: cust, items: $('#orderItems').val(), total: +$('#orderTotal').val() }); }
        save(LS.orders, arr); $('#orderModal').hide(); renderOrders(); renderUsers();
    });
    $(document).on('click', '.editOrderBtn', function () {
        const o = (load(LS.orders) || []).find(x => x.id === $(this).data('id'));
        if (o) { $('#orderId').val(o.id); $('#orderCustomerInput').val(o.customer); $('#orderItems').val(o.items); $('#orderTotal').val(o.total); $('#orderModal').show(); }
    });
    $(document).on('click', '.delOrderBtn', function () { let arr = load(LS.orders) || []; arr = arr.filter(o => o.id != $(this).data('id')); save(LS.orders, arr); renderOrders(); });

    // ========== SEED SAMPLE ==========
    $('#seedDataBtn').click(() => {
        save(LS.products, [{ id: uid('p'), name: 'Shoes', price: 2500 }, { id: uid('p'), name: 'Jacket', price: 4500 }]);
        save(LS.users, [{ id: uid('user'), name: 'Admin', email: 'admin@oms.local', password: 'admin123', role: 'admin' }, { id: uid('user'), name: 'Jane Doe', email: 'jane@doe.com', password: '123', role: 'customer' }]);
        save(LS.affiliates, [{ id: uid('a'), name: 'Partner A', code: 'AFF123' }]);
        save(LS.orders, [{ id: uid('o'), customer: 'Jane Doe', items: 'Shoes, Jacket', total: 7000 }]);
        toast("Sample data seeded"); renderAll();
    });

    // ========== RENDER ALL ==========
    function renderAll() { renderProducts(); renderUsers(); renderAffiliates(); renderOrders(); }

    // Auto login if user exists
    if (getCurrentUser()) { showApp(); }
});
