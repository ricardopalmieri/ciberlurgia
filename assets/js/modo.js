/* Decide 2D ou 3D antes da página pintar. Sem WebGL, com economia de dados,
   pouca memória, toque ou movimento reduzido, começa em 2D. A escolha do botão fica guardada. */
(function () {
  var d = document.documentElement, m = null, gl = false;
  try { m = localStorage.getItem('cbl-modo'); } catch (e) {}
  try { var c = document.createElement('canvas'); gl = !!(c.getContext('webgl2') || c.getContext('webgl')); } catch (e) {}
  if (!gl) m = '2d';
  if (m !== '2d' && m !== '3d') {
    var n = navigator.connection || {};
    var fraco = n.saveData || /(^|-)2g$/.test(n.effectiveType || '') ||
      (navigator.deviceMemory && navigator.deviceMemory < 4) ||
      matchMedia('(prefers-reduced-motion: reduce)').matches;
    m = fraco ? '2d' : '3d';
  }
  d.setAttribute('data-modo', m);
  d.setAttribute('data-webgl', gl ? '1' : '0');
})();
