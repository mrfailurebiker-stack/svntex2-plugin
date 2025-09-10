<?php
/**
 * Dedicated SVNTeX Products page (independent of WooCommerce shop UI)
 * Provides advanced search (name / category / SKU) + grid layout.
 */
if (!defined('ABSPATH')) exit;

// Enqueue brand styles
wp_enqueue_style('svntex2-style');
wp_enqueue_style('svntex2-landing');

// Capture search parameters
$search = sanitize_text_field($_GET['q'] ?? '');
$category = isset($_GET['cat']) ? (int) $_GET['cat'] : 0;
$sku = sanitize_text_field($_GET['sku'] ?? '');

// Build WP_Query args
$args = [
  'post_type' => 'svntex_product',
  'posts_per_page' => 24,
  'paged' => max(1, (int)($_GET['pg'] ?? 1)),
  's' => $search,
];
if ($category) {
  $args['tax_query'] = [ [ 'taxonomy' => 'svntex_category','field' => 'term_id','terms' => $category ] ];
}
// SKU filter via meta-like variant table search (simplified: search variants table)
$variant_ids = [];
if ($sku) {
  global $wpdb; $t = $wpdb->prefix.'svntex_product_variants';
  $pids = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT product_id FROM $t WHERE sku LIKE %s", '%'.$wpdb->esc_like($sku).'%'));
  if ($pids) { $args['post__in'] = array_map('intval',$pids); $args['posts_per_page'] = 48; }
  else { $args['post__in'] = [0]; }
}
$q = new WP_Query($args);

$cats = get_terms(['taxonomy'=>'svntex_category','hide_empty'=>false,'parent'=>0]);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?php bloginfo('name'); ?> – Products</title>
  <?php wp_head(); ?>
  <style>
  .svntex-products-shell{min-height:100vh;padding:clamp(1.5rem,2vw,2.5rem) clamp(1rem,3vw,2.5rem);max-width:1400px;margin:0 auto;}
  .prod-search-bar{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.75rem;align-items:end;background:rgba(255,255,255,0.08);padding:1rem 1.1rem;border:1px solid rgba(255,255,255,0.12);border-radius:20px;margin-bottom:1.8rem;backdrop-filter:blur(10px);}
  body:not(.dark) .prod-search-bar{background:rgba(255,255,255,.85);border-color:#e2e8f0;}
  .prod-search-bar label{font-size:.6rem;font-weight:600;letter-spacing:.6px;text-transform:uppercase;color:var(--svn-text-dim);display:block;margin-bottom:.35rem;}
  .prod-search-bar input[type=text], .prod-search-bar select{width:100%;padding:.65rem .75rem;border-radius:12px;border:1px solid rgba(255,255,255,0.25);background:rgba(15,23,42,.65);color:#fff;font-size:.85rem;}
  body:not(.dark) .prod-search-bar input[type=text], body:not(.dark) .prod-search-bar select{background:#fff;color:#0f172a;border-color:#cbd5e1;}
  .prod-search-bar button{padding:.75rem 1.15rem;border-radius:14px;border:0;background:linear-gradient(90deg,var(--svn-accent),var(--svn-accent-alt));color:#fff;font-weight:600;cursor:pointer;letter-spacing:.5px;font-size:.8rem;}
  .prod-headline{display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:1rem;margin-bottom:.65rem;}
  .prod-headline h1{margin:0;font-size:clamp(1.65rem,3vw,2.3rem);font-weight:700;letter-spacing:.5px;background:linear-gradient(90deg,var(--svn-accent),var(--svn-accent-alt));-webkit-background-clip:text;background-clip:text;color:transparent;}
  .prod-total{font-size:.75rem;color:var(--svn-text-dim);}
  .products-grid{display:grid;gap:clamp(.9rem,1.6vw,1.4rem);grid-template-columns:repeat(auto-fill,minmax(180px,1fr));}
  @media (min-width:900px){.products-grid{grid-template-columns:repeat(auto-fill,minmax(220px,1fr));}}
  .p-card{position:relative;display:flex;flex-direction:column;background:linear-gradient(150deg,rgba(255,255,255,.06),rgba(148,163,184,.04));border:1px solid rgba(255,255,255,.08);border-radius:22px;overflow:hidden;transition:.45s transform,.45s box-shadow,.45s border-color;backdrop-filter:blur(6px);}
  .p-card:hover{transform:translateY(-6px);box-shadow:0 14px 34px -12px rgba(219,39,119,.45),0 4px 12px rgba(0,0,0,.45);border-color:rgba(219,39,119,.4);}
  .p-thumb{display:block;width:100%;aspect-ratio:4/3;background:#0f172a;overflow:hidden;}
  .p-thumb img{width:100%;height:100%;object-fit:cover;display:block;}
  .p-thumb .ph{display:flex;align-items:center;justify-content:center;color:var(--svn-text-dim);font-size:.7rem;height:100%;}
  .p-body{padding:.8rem .85rem 1rem;display:flex;flex-direction:column;gap:.45rem;flex:1;}
  .p-title{margin:0;font-size:.85rem;line-height:1.3;font-weight:600;}
  .p-title a{text-decoration:none;color:#fff;}
  .p-title a:hover{text-decoration:underline;}
  .p-meta{font-size:.65rem;color:var(--svn-text-dim);display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;}
  .p-range{font-size:.6rem;opacity:.8;}
  .p-price{font-weight:700;font-size:.85rem;background:linear-gradient(90deg,var(--svn-accent),var(--svn-accent-alt));-webkit-background-clip:text;background-clip:text;color:transparent;}
  .p-rating{font-size:.6rem;color:#fbbf24;font-weight:600;}
  .p-actions{margin-top:auto;display:flex;gap:.45rem;}
  .btn-add{flex:1;padding:.55rem .65rem;border-radius:12px;border:0;background:linear-gradient(90deg,var(--svn-accent),var(--svn-accent-alt));color:#fff;font-weight:600;font-size:.65rem;cursor:pointer;letter-spacing:.5px;}
  .btn-add:hover{filter:brightness(1.1);}
  .empty{font-size:.8rem;color:var(--svn-text-dim);margin:1rem 0;}
  .pagination{margin:2rem 0 0;display:flex;gap:.5rem;flex-wrap:wrap;}
  .pagination a{padding:.5rem .8rem;border-radius:10px;text-decoration:none;font-size:.7rem;font-weight:600;background:rgba(255,255,255,.1);color:#fff;}
  .pagination a.active,.pagination a:hover{background:linear-gradient(90deg,var(--svn-accent),var(--svn-accent-alt));}
  body:not(.dark) .p-card{background:#fff;border-color:#e2e8f0;}
  body:not(.dark) .p-card:hover{box-shadow:0 8px 22px -6px rgba(0,0,0,.25);}  
  </style>
</head>
<body <?php body_class('svntex-landing svntex-products-page'); ?>>
<div class="svntex-products-shell">
  <header class="prod-headline">
    <h1>Products</h1>
    <div class="prod-total"><?php echo (int)$q->found_posts; ?> items</div>
  </header>
  <form class="prod-search-bar" method="get" action="">
    <div>
      <label>Name / Keyword</label>
      <input type="text" name="q" value="<?php echo esc_attr($search); ?>" placeholder="Search name..." />
    </div>
    <div>
      <label>Category</label>
      <select name="cat">
        <option value="">All</option>
        <?php foreach($cats as $c): ?>
          <option value="<?php echo (int)$c->term_id; ?>" <?php selected($category,$c->term_id); ?>><?php echo esc_html($c->name); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>SKU</label>
      <input type="text" name="sku" value="<?php echo esc_attr($sku); ?>" placeholder="SKU" />
    </div>
    <div style="align-self:stretch;display:flex;gap:.5rem;">
      <button type="submit">Search</button>
      <a href="<?php echo esc_url( remove_query_arg(['q','cat','sku','pg']) ); ?>" style="padding:.75rem 1rem;font-size:.7rem;font-weight:600;text-decoration:none;border-radius:14px;background:rgba(255,255,255,.15);color:#fff;display:inline-flex;align-items:center;">Reset</a>
    </div>
  </form>
  <?php if ( $q->have_posts() ): ?>
    <div class="products-grid">
      <?php while($q->have_posts()): $q->the_post(); ?>
        <?php
          // Derive price range from variants (min/max active prices)
          global $wpdb; $vt=$wpdb->prefix.'svntex_product_variants';
          $range = $wpdb->get_row($wpdb->prepare("SELECT MIN(price) as min_p, MAX(price) as max_p FROM $vt WHERE product_id=%d AND active=1 AND price IS NOT NULL", get_the_ID()));
          $price_disp = '—';
          if ($range && $range->min_p !== null) {
            $min_p = (float)$range->min_p; $max_p = (float)$range->max_p;
            if ($min_p === $max_p) {
              $price_disp = function_exists('wc_price') ? wc_price($min_p) : esc_html(number_format($min_p,2));
            } else {
              $min_fmt = function_exists('wc_price') ? wc_price($min_p) : esc_html(number_format($min_p,2));
              $max_fmt = function_exists('wc_price') ? wc_price($max_p) : esc_html(number_format($max_p,2));
              $price_disp = $min_fmt . ' – ' . $max_fmt;
            }
          }
          $rating_html = '';
          if (function_exists('wc_get_rating_html')) { $avg = get_post_meta(get_the_ID(),'_wc_average_rating',true); if($avg){ $rating_html = '<span class="p-rating">'.str_repeat('★', (int)round($avg)).'</span>'; } }
        ?>
        <article class="p-card">
          <a class="p-thumb" href="<?php the_permalink(); ?>">
            <?php if ( has_post_thumbnail() ) { the_post_thumbnail('medium'); } else { echo '<div class="ph">No Image</div>'; } ?>
          </a>
          <div class="p-body">
            <h3 class="p-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
            <div class="p-meta">
              <span class="p-price"><?php echo $price_disp; ?></span>
              <?php echo $rating_html; ?>
            </div>
            <div class="p-actions" data-product-actions data-product-id="<?php the_ID(); ?>" data-variant-id="0">
              <button type="button" class="btn-add" data-add>Add to Cart</button>
            </div>
          </div>
        </article>
      <?php endwhile; wp_reset_postdata(); ?>
    </div>
    <?php
      $current = max(1,(int)($args['paged'])); $total = (int)$q->max_num_pages;
      if ($total>1): echo '<nav class="pagination" aria-label="Pagination">';
        for($i=1;$i<=$total;$i++){
          $url = add_query_arg( 'pg', $i );
          echo '<a href="'.esc_url($url).'" class="'.($i===$current?'active':'').'">'.$i.'</a>';
        }
        echo '</nav>';
      endif;
    ?>
  <?php else: ?>
    <p class="empty">No products found.</p>
  <?php endif; ?>
</div>
<?php wp_footer(); ?>
<script>
// AJAX add to cart
document.addEventListener('click', function(e){
  const btn = e.target.closest('[data-add]'); if(!btn) return;
  const wrap = btn.closest('[data-product-actions]'); if(!wrap) return;
  if(btn.dataset.loading) return;
  btn.dataset.loading = '1';
  const pid = wrap.getAttribute('data-product-id');
  const vid = wrap.getAttribute('data-variant-id') || 0;
  const orig = btn.textContent; btn.textContent = 'Adding…';
  fetch('<?php echo esc_url_raw( rest_url('svntex2/v1/cart/add') ); ?>', {
    method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ product_id: pid, variant_id: vid, qty: 1 })
  }).then(r=>r.json()).then(data=>{
    btn.textContent = 'Added'; setTimeout(()=>{ btn.textContent = orig; delete btn.dataset.loading; }, 1200);
  }).catch(()=>{ btn.textContent='Error'; setTimeout(()=>{ btn.textContent=orig; delete btn.dataset.loading; },1400); });
});
</script>
</body>
</html>
