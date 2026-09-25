// Minimal frontend interactions: cart, coupons, mock checkout
const Cart = {
  key: 'dh_cart',
  items(){ return JSON.parse(localStorage.getItem(this.key) || '[]') },
  save(items){ localStorage.setItem(this.key, JSON.stringify(items)) },
  add(item){ const items=this.items(); const found = items.find(i=>i.id==item.id); if(found){found.qty+=item.qty}else{items.push(item);} this.save(items); },
  remove(id){ const items=this.items().filter(i=>i.id!=id); this.save(items); }
}

function formatPrice(v){ return '$'+Number(v).toFixed(2); }

// Example: render product cards if container exists
document.addEventListener('DOMContentLoaded', ()=>{
  const grid = document.querySelector('#productsGrid');
  if(grid){
    // fetch products from backend stub
    fetch('/backend/endpoints/products_list.php').then(r=>r.json()).then(data=>{
      data.products.slice(0,12).forEach(p=>{
        const el=document.createElement('div'); el.className='card fade-in';
        el.innerHTML = `<img src='${p.thumbnail||"/frontend/assets/placeholder.png"}' alt='${p.title}'><h3>${p.title}</h3><div class='row'><div class='price'>${formatPrice(p.price)}</div><button class='cta' data-id='${p.id}'>Add</button></div>`;
        grid.appendChild(el);
      });
      grid.addEventListener('click', e=>{ if(e.target.matches('.cta')){ const id=e.target.dataset.id; Cart.add({id: id, qty:1}); alert('Added to cart'); } });
    }).catch(err=>{
      // fallback sample products
      for(let i=1;i<=6;i++){ const el=document.createElement('div'); el.className='card'; el.innerHTML=`<h3>Sample Product ${i}</h3><div class='row'><div class='price'>$9.99</div><button class='cta' onclick="Cart.add({id:${i},qty:1});alert('Added')">Add</button></div>`; grid.appendChild(el); }
    });
  }
});
