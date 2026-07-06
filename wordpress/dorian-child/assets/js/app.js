(function(){
  var RM=matchMedia('(prefers-reduced-motion:reduce)').matches;
  var screens=[].slice.call(document.querySelectorAll('.screen'));
  var head=document.getElementById('head'),burger=document.getElementById('burger'),
      nav=document.getElementById('nav'),lift=document.getElementById('lift');
  var lenis=null;
  var shaftST=null;                 // the shaft's ScrollTrigger (set once built) — lets the panel call floors

  /* #16 — elevator call panel: a floor button takes you to that floor */
  function goFloor(idx){
    var stops=document.querySelectorAll('#floors .floorstop');
    if(shaftST && stops.length>1){
      var y=shaftST.start + (idx/(stops.length-1))*(shaftST.end-shaftST.start);
      if(lenis) lenis.scrollTo(y,{duration:1.5}); else window.scrollTo({top:y,behavior:'smooth'});
    } else if(stops[idx]){
      if(lenis) lenis.scrollTo(stops[idx],{duration:1.5}); else stops[idx].scrollIntoView({behavior:'smooth'});
    }
    nav.classList.remove('open');burger.classList.remove('open');
  }
  document.querySelectorAll('.lift__btn').forEach(function(b){
    b.addEventListener('click',function(){ goFloor(+b.dataset.goFloor); });
  });

  /* preloader: count up (holds at 90 until loaded), then part the elevator doors */
  (function(){
    var pre=document.getElementById('pre'),num=document.getElementById('pcount'),bar=document.getElementById('pbar');
    if(!pre) return;
    var v=0,done=false,loaded=false;
    window.addEventListener('load',function(){loaded=true;});
    setTimeout(function(){loaded=true;},3500);          // safety: never stall
    function finish(){
      if(done) return; done=true;
      pre.classList.add('done');
      document.documentElement.classList.add('loaded');
      setTimeout(function(){pre.classList.add('gone');},1500);
    }
    var iv=setInterval(function(){
      var target=loaded?100:90;
      if(v<target) v+=Math.max(1,Math.round((target-v)*0.10));
      if(v>100) v=100;
      if(num) num.textContent=v; if(bar) bar.style.width=v+'%';
      if(v>=100){ clearInterval(iv); setTimeout(finish,260); }
    },45);
  })();

  /* embers */
  if(!RM){
    var box=document.getElementById('embers');
    for(var i=0;i<18;i++){
      var e=document.createElement('span');e.className='ember';
      var s=2+Math.random()*4;
      e.style.cssText='left:'+(Math.random()*100)+'%;width:'+s+'px;height:'+s+'px;animation-duration:'+(7+Math.random()*8)+'s;animation-delay:'+(Math.random()*9)+'s';
      box.appendChild(e);
    }
  }

  /* dot nav */
  var dots=document.getElementById('dots');
  screens.forEach(function(s,i){
    var b=document.createElement('button');b.dataset.l=s.dataset.name||('0'+(i+1));b.dataset.idx=i;
    b.addEventListener('click',function(){goTo(i);});
    dots.appendChild(b);
  });
  var dotBtns=[].slice.call(dots.children);

  /* editorial section numbers (01..) — injected into every screen but the hero */
  screens.forEach(function(s,i){
    if(i===0) return;
    var num=document.createElement('div'); num.className='sec-num';
    num.innerHTML='<span class="nm">'+(s.dataset.name||'')+'</span><b>'+('0'+i).slice(-2)+'</b>';
    s.appendChild(num);
  });

  /* faint floating gold dust, site-wide */
  if(!RM){
    var dustBox=document.getElementById('dust');
    if(dustBox){
      for(var k=0;k<16;k++){
        var sp=document.createElement('span'); var sz=2+Math.random()*3.4;
        sp.style.cssText='left:'+(Math.random()*100)+'%;bottom:-10px;width:'+sz+'px;height:'+sz+'px;animation-duration:'+(16+Math.random()*16)+'s;animation-delay:'+(Math.random()*18)+'s';
        dustBox.appendChild(sp);
      }
    }
  }

  /* custom cursor + magnetic buttons (fine pointers, motion on) */
  if(!RM && matchMedia('(pointer:fine)').matches){
    document.documentElement.classList.add('has-cursor');
    var cDot=document.getElementById('curDot'),cRing=document.getElementById('curRing');
    var mX=innerWidth/2,mY=innerHeight/2,lX=mX,lY=mY;
    window.addEventListener('pointermove',function(e){mX=e.clientX;mY=e.clientY;
      if(cDot) cDot.style.transform='translate('+mX+'px,'+mY+'px) translate(-50%,-50%)';},{passive:true});
    (function ring(){lX+=(mX-lX)*0.18;lY+=(mY-lY)*0.18;
      if(cRing) cRing.style.transform='translate('+lX+'px,'+lY+'px) translate(-50%,-50%)';
      requestAnimationFrame(ring);})();
    var HOVER='a,button,.magnetic,.tcard,.gcard,.tab,.phone,.chip';
    document.addEventListener('pointerover',function(e){if(cRing&&e.target.closest&&e.target.closest(HOVER))cRing.classList.add('hover');});
    document.addEventListener('pointerout',function(e){if(cRing&&e.target.closest&&e.target.closest(HOVER))cRing.classList.remove('hover');});
    window.addEventListener('pointerdown',function(){cRing&&cRing.classList.add('down');});
    window.addEventListener('pointerup',function(){cRing&&cRing.classList.remove('down');});
    document.querySelectorAll('.btn,.team__nav button,.foot__soc a').forEach(function(el){
      el.classList.add('magnetic');
      el.addEventListener('pointermove',function(e){var r=el.getBoundingClientRect();
        el.style.transform='translate('+((e.clientX-r.left-r.width/2)*0.3)+'px,'+((e.clientY-r.top-r.height/2)*0.4)+'px)';});
      el.addEventListener('pointerleave',function(){el.style.transform='';});
    });
  }

  /* ambient tone behind everything eases between section moods */
  var TONE={hero:'#ECDEC9',story:'#ECDEC9',floors:'#0b2744',services:'#0a0805',team:'#081726',gift:'#ECDEC9',reserve:'#081726'};
  function setTone(sec){
    var c=TONE[sec.id]||(sec.dataset.theme==='dark'?'#081726':'#ECDEC9');
    var t=document.getElementById('tone'); if(t) t.style.backgroundColor=c;
    document.body.style.backgroundColor=c;
  }

  function goTo(i){
    var t=screens[i]; if(!t) return;
    if(lenis){ lenis.scrollTo(t,{duration:1.4,offset:0}); }
    else{ t.scrollIntoView({behavior:RM?'auto':'smooth'}); }
  }
  function scrollToEl(t){ if(!t) return; if(lenis){lenis.scrollTo(t,{duration:1.4});} else {t.scrollIntoView({behavior:RM?'auto':'smooth'});} }
  /* nav resolves its target by href (#id) so adding sections never breaks navigation */
  document.querySelectorAll('[data-go]').forEach(function(a){
    a.addEventListener('click',function(ev){ev.preventDefault();
      var href=a.getAttribute('href'),t=(href&&href.charAt(0)==='#')?document.querySelector(href):null;
      if(t) scrollToEl(t); else goTo(+a.dataset.go);
      nav.classList.remove('open');burger.classList.remove('open');});
  });

  /* header + mobile nav */
  burger.addEventListener('click',function(){nav.classList.toggle('open');burger.classList.toggle('open');});
  function onScrollY(y){head.classList.toggle('compact',y>40);}

  /* active section: theme, dots, header, elevator */
  function setActive(sec){
    if(!sec) return;
    document.documentElement.dataset.theme=sec.dataset.theme||'light';
    setTone(sec);
    var idx=screens.indexOf(sec);
    dotBtns.forEach(function(b,i){b.classList.toggle('on',i===idx);});
    if(sec.dataset.floor || sec.id==='floors'){lift.classList.add('show'); if(sec.dataset.floor) lift.dataset.active=sec.dataset.floor;}
    else{lift.classList.remove('show');}
  }

  /* services tabs */
  document.querySelectorAll('.tab').forEach(function(t){
    t.addEventListener('click',function(){
      var k=t.dataset.tab;
      document.querySelectorAll('.tab').forEach(function(x){x.classList.toggle('active',x===t);});
      document.querySelectorAll('.panel').forEach(function(p){p.classList.toggle('active',p.dataset.panel===k);});
      if(window.ScrollTrigger) ScrollTrigger.refresh();
    });
  });

  /* team rail: buttons + drag (team section may be absent on this page) */
  var rail=document.getElementById('teamRail');
  if(rail){
  rail.setAttribute('data-lenis-prevent','');
  var _step=function(){return Math.min(rail.clientWidth*.8,330);};
  var _next=document.getElementById('teamNext'),_prev=document.getElementById('teamPrev');
  if(_next) _next.addEventListener('click',function(){rail.scrollBy({left:-_step(),behavior:'smooth'});});
  if(_prev) _prev.addEventListener('click',function(){rail.scrollBy({left:_step(),behavior:'smooth'});});
  (function(){var down=false,sx=0,sl=0,moved=false;
    rail.addEventListener('pointerdown',function(e){down=true;moved=false;sx=e.clientX;sl=rail.scrollLeft;rail.setPointerCapture(e.pointerId);rail.classList.add('drag');});
    rail.addEventListener('pointermove',function(e){if(!down)return;var d=e.clientX-sx;if(Math.abs(d)>4)moved=true;rail.scrollLeft=sl-d;});
    function up(){down=false;rail.classList.remove('drag');}
    rail.addEventListener('pointerup',up);rail.addEventListener('pointercancel',up);rail.addEventListener('pointerleave',up);
    rail.addEventListener('click',function(e){if(moved){e.preventDefault();e.stopPropagation();}},true);
  })();
  }

  /* gift 3D tilt */
  if(!RM && matchMedia('(pointer:fine)').matches){
    document.querySelectorAll('.gcard').forEach(function(c){
      var inner=c.querySelector('.gcard__inner');
      c.addEventListener('pointermove',function(e){var r=c.getBoundingClientRect();
        var rx=((e.clientY-r.top)/r.height-.5)*-14,ry=((e.clientX-r.left)/r.width-.5)*16;
        inner.style.transform='rotateX('+rx+'deg) rotateY('+ry+'deg) translateZ(10px)';});
      c.addEventListener('pointerleave',function(){inner.style.transform='';});
    });
  }

  /* keyboard up/down -> jump section */
  window.addEventListener('keydown',function(e){
    if(e.key==='ArrowDown'||e.key==='PageDown'){var c=current();if(c<screens.length-1){e.preventDefault();goTo(c+1);}}
    if(e.key==='ArrowUp'||e.key==='PageUp'){var c2=current();if(c2>0){e.preventDefault();goTo(c2-1);}}
  });
  function current(){ // nearest screen to viewport centre (pin-safe)
    var c=innerHeight/2,best=0,bd=1e9;
    for(var i=0;i<screens.length;i++){var r=screens[i].getBoundingClientRect();var d=Math.abs((r.top+r.bottom)/2-c);if(d<bd){bd=d;best=i;}}
    return best;
  }

  /* ============================ CINEMATIC ENGINE ============================ */
  function initCinema(){
    var gsap=window.gsap;
    gsap.registerPlugin(ScrollTrigger);

    /* Lenis — heavy, silky, free continuous scroll (lerp-based, no snapping anywhere) */
    var shaftRenders=[].slice.call(document.querySelectorAll('.floor .render'));
    function setShaftBlur(v){
      var b=Math.min(Math.abs(v)*0.16,5);                 // velocity -> subtle motion blur
      for(var i=0;i<shaftRenders.length;i++) shaftRenders[i].style.setProperty('--shaftblur',b.toFixed(2)+'px');
    }
    if(!RM && window.Lenis){
      lenis=new Lenis({
        lerp:0.06,                                         // very smooth, weighty glide
        smoothWheel:true,wheelMultiplier:0.9,touchMultiplier:1.6,syncTouch:false
      });
      lenis.on('scroll',function(e){
        ScrollTrigger.update();
        onScrollY(e.scroll!=null?e.scroll:window.scrollY);
        setShaftBlur(e.velocity||0);                       // decays to 0 as Lenis settles -> blur clears itself
      });
      gsap.ticker.add(function(t){lenis.raf(t*1000);});
      gsap.ticker.lagSmoothing(0);
      window.__dorianLenis=lenis;   // expose so the elevator can freeze/resume page scroll
    }else{
      window.addEventListener('scroll',function(){onScrollY(window.scrollY);},{passive:true});
    }

    /* per-section: entrance reveal (reuses .anim CSS) + theme/dots/elevator context.
       The shaft section is skipped for whole-section reveal — its floors reveal one-by-one. */
    screens.forEach(function(sec){
      if(sec.id!=='floors'){
        ScrollTrigger.create({trigger:sec,start:'top 78%',
          onEnter:function(){sec.classList.add('active');},
          onLeaveBack:function(){sec.classList.remove('active');}});
      }
      ScrollTrigger.create({trigger:sec,start:'top center',end:'bottom center',
        onToggle:function(self){if(self.isActive)setActive(sec);}});
    });

    if(!RM){
      /* HERO — cinematic depth as it travels away */
      gsap.timeline({scrollTrigger:{trigger:'#hero',start:'top top',end:'bottom top',scrub:1}})
        .to('.hero__wm',{yPercent:-26,opacity:0,ease:'none'},0)
        .to('.hero__portrait',{yPercent:-20,scale:1.06,ease:'none'},0)
        .to('.hero__eyebrow,.hero__tag',{y:-70,opacity:0,ease:'none'},0)
        .to('.hero__logo,.hero__sub,.hero__base,.hero__ctas',{y:-34,opacity:0,ease:'none'},.05)
        .to('.hero__frame,.cue',{opacity:0,ease:'none'},0);

      /* ===== ELEVATOR SHAFT — pin the window, translate the rail, ride the floors ===== */
      (function(){
        var section=document.getElementById('floors');
        var rail=document.querySelector('.shaft__rail');
        var stops=gsap.utils.toArray('#floors .floorstop');
        if(!section||!rail||!stops.length) return;
        var n=stops.length;
        section.classList.add('is-pinned');          // switch CSS into windowed/clipped mode

        var FT={'2':'#1e86d6','1':'#d9b65a','B':'#caa26a'};                     // cool / warm-gold / warm-cream
        var cur=-1;
        function setStop(idx){
          if(idx===cur) return; cur=idx;
          stops.forEach(function(s,i){s.classList.toggle('inview',i===idx);}); // word + clip-path reveal per floor
          var fl=stops[idx].dataset.floor;
          lift.dataset.active=fl;                                              // light label + tick
          section.style.setProperty('--ftint',FT[fl]||'transparent');         // smooth light wash per floor
        }

        var tl=gsap.timeline({scrollTrigger:{
          trigger:section,start:'top top',end:'+='+((n-1)*100)+'%',
          pin:true,scrub:1,anticipatePin:1,invalidateOnRefresh:true,
          onToggle:function(self){ lift.classList.toggle('show',self.isActive); },
          onUpdate:function(self){
            var p=self.progress;
            lift.style.setProperty('--carpos',(10+p*80).toFixed(2)+'%');       // cabin glides 10%→90%
            setStop(Math.round(p*(n-1)));
          }
        }});
        shaftST=tl.scrollTrigger;                                              // expose to the call panel
        // the rail rises so each floor passes up through the fixed window
        tl.to(rail,{yPercent:-100*(n-1)/n,ease:'none'},0);
        // depth: the giant floor number drifts on a slower layer, the render eases through the frame
        stops.forEach(function(st){
          var g=st.querySelector('.floor__ghost'), r=st.querySelector('.render');
          if(g) tl.fromTo(g,{yPercent:-7},{yPercent:7,ease:'none'},0);
          if(r) tl.fromTo(r,{yPercent:6,scale:.975},{yPercent:-6,scale:1,ease:'none'},0);
        });
        // reveal the first floor only once the shaft is actually entered (fresh word-stagger on arrival)
      })();

      /* ===== TEAM — pinned horizontal gallery advanced by vertical scroll (Shehata-style) ===== */
      (function(){
        var team=document.getElementById('team'),trail=document.getElementById('teamRail'),prog=document.getElementById('teamProg');
        if(!team||!trail) return;
        if(!matchMedia('(min-width:880px)').matches) return;          // phones keep native drag
        trail.removeAttribute('data-lenis-prevent');
        trail.classList.remove('anim');                              // GSAP owns the rail transform now
        team.classList.add('is-gallery');
        // travel = hidden overflow so the LAST card reaches the left edge (one scroll shows everyone)
        var travel=function(){ return Math.max(0, trail.scrollWidth - window.innerWidth + 96); };
        if(travel()<40){ team.classList.remove('is-gallery'); return; }
        gsap.to(trail,{x:function(){return -travel();},ease:'none',
          scrollTrigger:{trigger:team,start:'top top',end:function(){return '+='+travel();},
            pin:true,scrub:1,invalidateOnRefresh:true,refreshPriority:1,
            onUpdate:function(self){ if(prog) prog.style.width=(self.progress*100).toFixed(1)+'%'; }}});
        ScrollTrigger.refresh();
      })();

      /* STORY — building drifts against the scroll */
      var bld=document.querySelector('.bld');
      if(bld) gsap.fromTo(bld,{yPercent:9},{yPercent:-9,ease:'none',
        scrollTrigger:{trigger:'#story',start:'top bottom',end:'bottom top',scrub:true}});
    }

    /* first screen live immediately */
    screens[0].classList.add('active');setActive(screens[0]);
    ScrollTrigger.refresh();
    window.addEventListener('load',function(){ScrollTrigger.refresh();});
  }

  /* ============================ OFFLINE FALLBACK ============================ */
  function initFallback(){
    window.addEventListener('scroll',function(){onScrollY(window.scrollY);},{passive:true});
    var ioReveal=new IntersectionObserver(function(entries){
      entries.forEach(function(e){if(e.intersectionRatio>=.45)e.target.classList.add('active');});
    },{threshold:[0,.45]});
    var ioActive=new IntersectionObserver(function(entries){
      entries.forEach(function(e){if(e.isIntersecting)setActive(e.target);});
    },{threshold:.4,rootMargin:'-30% 0px -30% 0px'});
    screens.forEach(function(s){ioReveal.observe(s);ioActive.observe(s);});
    // floors are stacked (not pinned) here — reveal each stop + light its elevator floor on entry
    var stops=[].slice.call(document.querySelectorAll('#floors .floorstop'));
    var floorsSec=document.getElementById('floors'),FT2={'2':'#1e86d6','1':'#d9b65a','B':'#caa26a'};
    var ioStop=new IntersectionObserver(function(entries){
      entries.forEach(function(e){if(e.isIntersecting){e.target.classList.add('inview');lift.dataset.active=e.target.dataset.floor;
        if(floorsSec) floorsSec.style.setProperty('--ftint',FT2[e.target.dataset.floor]||'transparent');}});
    },{threshold:.45});
    stops.forEach(function(s){ioStop.observe(s);});
    screens[0].classList.add('active');setActive(screens[0]);
    setTimeout(function(){screens.forEach(function(s){var r=s.getBoundingClientRect();if(r.top<innerHeight&&r.bottom>0)s.classList.add('active');});},1500);
  }

  /* word-by-word reveal: split plain-text headings into staggered .word spans (RTL-safe) */
  function splitWords(el){
    var w=0;
    [].slice.call(el.childNodes).forEach(function(node){
      if(node.nodeType===3){ // text node -> wrap each word, keep whitespace
        var frag=document.createDocumentFragment();
        node.textContent.split(/(\s+)/).forEach(function(part){
          if(part==='' ) return;
          if(/^\s+$/.test(part)){ frag.appendChild(document.createTextNode(part)); }
          else{ var s=document.createElement('span'); s.className='word'; s.style.setProperty('--w',w++); s.textContent=part; frag.appendChild(s); }
        });
        el.replaceChild(frag,node);
      } else if(node.nodeType===1){ // existing inline element (e.g. .serif) -> one word unit
        node.classList.add('word'); node.style.setProperty('--w',w++);
      }
    });
    el.classList.remove('anim'); // word spans own the reveal now
  }
  ['.story__copy h2','.floor__fa','.services__inner h2','.team__head h2','.gift__inner h2','.shop__inner h2','.reserve h2']
    .forEach(function(sel){ document.querySelectorAll(sel).forEach(splitWords); });

  /* boot — prefer the cinematic engine, degrade gracefully */
  if(window.gsap && window.ScrollTrigger){ try{initCinema();}catch(err){initFallback();} }
  else{ initFallback(); }
})();
