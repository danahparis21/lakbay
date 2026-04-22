/**
 * LAKBAY — Main JavaScript
 */
(function(){
  'use strict';

  // Navbar scroll shadow
  var navbar = document.querySelector('.navbar');
  if(navbar){
    window.addEventListener('scroll',function(){
      navbar.style.boxShadow = window.scrollY>8 ? '0 2px 20px rgba(8,37,53,0.12)' : '0 1px 0 rgba(37,74,90,0.08)';
    },{passive:true});
  }

  // Fade-in on scroll
  if('IntersectionObserver' in window){
    var obs = new IntersectionObserver(function(entries){
      entries.forEach(function(e){ if(e.isIntersecting){ e.target.classList.add('fade-in'); obs.unobserve(e.target); } });
    },{threshold:0.1});
    document.querySelectorAll('.card:not(.fade-in)').forEach(function(el){ obs.observe(el); });
  }

  // Smooth anchor scroll
  document.querySelectorAll('a[href^="#"]').forEach(function(a){
    a.addEventListener('click',function(e){
      var t=document.querySelector(this.getAttribute('href'));
      if(t){ e.preventDefault(); window.scrollTo({top:t.getBoundingClientRect().top+window.pageYOffset-84,behavior:'smooth'}); }
    });
  });

  // Dashboard sidebar mobile overlay
  var sidebarToggle = document.getElementById('sidebarToggle');
  var dashSidebar   = document.getElementById('dashSidebar');
  if(sidebarToggle && dashSidebar){
    var overlay = document.createElement('div');
    overlay.style.cssText='position:fixed;inset:0;background:rgba(8,37,53,0.45);z-index:299;display:none;';
    document.body.appendChild(overlay);
    overlay.addEventListener('click',function(){ dashSidebar.classList.remove('open'); overlay.style.display='none'; });
    sidebarToggle.addEventListener('click',function(){
      var open=dashSidebar.classList.toggle('open');
      overlay.style.display=open?'block':'none';
    });
  }

  // Date inputs — set min to today
  document.querySelectorAll('input[type="date"]').forEach(function(inp){
    if(!inp.min) inp.min=new Date().toISOString().split('T')[0];
  });

  // Img error fallback
  document.querySelectorAll('img').forEach(function(img){
    img.addEventListener('error',function(){
      this.src='data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="400" height="240"><rect fill="%23D4E1E7" width="400" height="240"/><text fill="%23254A5A" font-size="13" font-family="sans-serif" x="50%" y="50%" text-anchor="middle" dominant-baseline="middle">Image unavailable</text></svg>';
    });
  });

  // Toast fallback
  if(typeof showToast==='undefined'){
    window.showToast=function(msg,type){
      var t=document.createElement('div');
      t.className='toast toast-'+(type||'success');
      t.innerHTML='<span>'+msg+'</span>';
      var c=document.getElementById('toastContainer');
      if(c){ c.appendChild(t); setTimeout(function(){ t.style.opacity='0'; t.style.transition='opacity 0.3s'; setTimeout(function(){ t.remove(); },300); },3500); }
    };
  }
})();
