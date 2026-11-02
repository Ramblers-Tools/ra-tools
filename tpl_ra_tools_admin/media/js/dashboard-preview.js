/**
 * PROTOTYPE ONLY — swaps com_ra_tools dashboard block-header text for product logos.
 *
 * No com_ra_tools files are read or modified. This walks the existing rendered
 * .block-header h3 elements (from com_ra_tools/administrator/tmpl/dashboard/default.php)
 * and, for blocks whose title matches a known product, replaces the text with the
 * product's lockup image (kept accessible via alt text). Blocks without a mapped
 * logo are left as plain text. Logos are copied from mockup-assets/ for this test only;
 * per the handover's artwork split, product lockups belong in com_ra_tools's own media
 * if this direction is adopted for real.
 */
(function () {
  'use strict';

  var base = (window.raToolsAdminDashboardPreviewBase || '/media/templates/administrator/ra_tools_admin/images/dashboard-preview/');

  var logos = {
    'System Tools': base + 'ra-tools-lockup.svg',
    'Mail Manager': base + 'ra-mailman-lockup.svg',
    'Members': base + 'ra-members-lockup.svg',
    'Events': base + 'ra-events-lockup.svg',
    'Events & Bookings': base + 'ra-events-lockup.svg',
    'RA SSO': base + 'ra-sso-lockup.svg',
    'RA Delivery': base + 'ra-delivery-lockup.svg',
  };

  // Organisation is the exception to the rest: it keeps its text label
  // alongside the logo rather than the logo fully replacing it (it's core
  // Ramblers organisation data, not a distinct "product" like the others).
  // PROTOTYPE-ONLY external hotlink to ramblers.org.uk for this evaluation —
  // if this direction is adopted for real, vendor a proper local copy instead
  // (with rights confirmed) rather than hotlinking cross-origin in production.
  var logosKeepText = {
    'Organisation': 'https://www.ramblers.org.uk/_nuxt/img/horiz-colour.8829dc8.svg',
  };

  document.querySelectorAll('.block-header h3').forEach(function (heading) {
    var title = heading.textContent.trim();

    if (logosKeepText[title]) {
      var keepImg = document.createElement('img');
      keepImg.src = logosKeepText[title];
      keepImg.alt = '';
      keepImg.loading = 'eager';

      // Wrap the remaining text in its own span so it can be styled to match
      // the logo's wordmark (bold uppercase) independently of the <h3>.
      var label = document.createElement('span');
      label.className = 'ra-dashboard-org-label';
      label.textContent = title;

      heading.textContent = '';
      heading.appendChild(keepImg);
      heading.appendChild(label);
      heading.classList.add('ra-dashboard-logo-header', 'ra-dashboard-logo-header-with-text');
      return;
    }

    var src = logos[title];

    if (!src) {
      return;
    }

    var img = document.createElement('img');
    img.src = src;
    img.alt = title;
    img.loading = 'eager';
    heading.textContent = '';
    heading.appendChild(img);
    heading.classList.add('ra-dashboard-logo-header');
  });

  var grid = document.querySelector('.dashboard-grid');

  if (!grid) {
    return;
  }

  var blocks = Array.prototype.slice.call(grid.querySelectorAll('.dashboard-block'));

  if (!blocks.length) {
    return;
  }

  // com_ra_tools now lays its dashboard out as three columns. CSS Grid syncs
  // row heights across columns, leaving large gaps under shorter tiles paired
  // with a taller neighbour, so instead this rebuilds .dashboard-grid into
  // independent flex columns (masonry-style). Tiles keep the component's own
  // order, each placed into whichever column is currently shortest.
  //
  // The column count (3, 2 or 1) follows the grid's own width rather than the
  // window's, so the admin sidebar being open or closed is accounted for —
  // a column is only added when every column can be at least this wide.
  var minColumnWidth = 300;
  var columnGap = 20;
  var maxColumns = 3;

  // Natural tile heights, measured once while still in the component's grid
  // (after the logo swap above, which changes header heights).
  var heights = blocks.map(function (block) { return block.offsetHeight; });

  function columnCount() {
    var width = grid.clientWidth;
    var count = Math.floor((width + columnGap) / (minColumnWidth + columnGap));

    return Math.max(1, Math.min(maxColumns, count));
  }

  var currentCount = 0;

  function layout() {
    var count = columnCount();

    if (count === currentCount) {
      return;
    }

    currentCount = count;

    var columns = [];
    var totals = [];

    for (var i = 0; i < count; i++) {
      var column = document.createElement('div');
      column.className = 'ra-dashboard-column';
      columns.push(column);
      totals.push(0);
    }

    blocks.forEach(function (block, index) {
      var shortest = totals.indexOf(Math.min.apply(null, totals));
      columns[shortest].appendChild(block);
      totals[shortest] += heights[index];
    });

    grid.textContent = '';
    columns.forEach(function (column) { grid.appendChild(column); });
    grid.classList.add('ra-dashboard-columns');
  }

  layout();

  if (window.ResizeObserver) {
    new ResizeObserver(layout).observe(grid);
  } else {
    window.addEventListener('resize', layout);
  }
})();
