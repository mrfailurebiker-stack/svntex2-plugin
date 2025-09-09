<?php if(!defined('ABSPATH')) exit; ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title><?php bloginfo('name'); ?> – Welcome</title>
<?php wp_head(); ?>
<link rel="stylesheet" href="<?php echo esc_url( plugin_dir_url(SVNTEX2_PLUGIN_FILE).'assets/css/landing.css?v='.SVNTEX2_VERSION ); ?>" />
</head>
<body <?php body_class('svntex-landing'); ?>>
<div class="svn-landing-shell">
  <div class="shape s1"></div>
  <div class="shape s2"></div>
  <div class="shape s3"></div>
  <header class="svn-landing-nav" role="banner">
    <div class="brand">SVNTeX</div>
    <nav class="nav-products" aria-label="Products">
      <a class="nav-link" href="<?php echo esc_url( get_post_type_archive_link('svntex_product') ); ?>">Products</a>
      <?php
        $terms = get_terms([
          'taxonomy' => 'svntex_category',
          'parent' => 0,
          'hide_empty' => false,
        ]);
        if ( ! is_wp_error($terms) && ! empty($terms) ) :
      ?>
        <select class="product-category-select" aria-label="Choose category" onchange="if(this.value){window.location.href=this.value;}">
          <option value="">All Categories</option>
          <?php foreach($terms as $t): $link = get_term_link($t); if (is_wp_error($link)) continue; ?>
            <option value="<?php echo esc_url($link); ?>"><?php echo esc_html($t->name); ?></option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
      <form class="product-search" role="search" method="get" action="<?php echo esc_url( home_url('/') ); ?>">
        <label class="screen-reader-text" for="svntex-search">Search products</label>
        <input id="svntex-search" type="search" name="s" placeholder="Search products..." value="" />
        <input type="hidden" name="post_type" value="svntex_product" />
        <button type="submit">Search</button>
      </form>
    </nav>
    <nav class="nav-actions" aria-label="Primary">
      <a href="<?php echo esc_url( site_url('/'.SVNTEX2_LOGIN_SLUG.'/') ); ?>">Log In</a>
      <a href="<?php echo esc_url( site_url('/'.SVNTEX2_REGISTER_SLUG.'/') ); ?>">Sign Up</a>
    </nav>
  </header>
  <main class="hero" role="main">
    <h1>Welcome to SVNTeX</h1>
    <p class="sub">Your gateway to smart customer wallet management and rewards – referrals, performance bonuses, withdrawals and KYC in one streamlined platform.</p>
    <div class="cta-bar">
      <a class="btn" href="<?php echo esc_url( site_url('/'.SVNTEX2_REGISTER_SLUG.'/') ); ?>" aria-label="Create your SVNTeX account">Get Started</a>
      <a class="btn alt" href="<?php echo esc_url( site_url('/'.SVNTEX2_LOGIN_SLUG.'/') ); ?>">Log In</a>
    </div>
    <section class="feature-grid" aria-label="Key Features">
      <?php
      $features = [
        ['icon'=>'wallet','title'=>'Unified Wallet','desc'=>'Track income, top-ups, bonuses and withdrawals with transparent ledgers.'],
        ['icon'=>'referral','title'=>'Referral Engine','desc'=>'Tiered commissions & one-time activation bonuses to reward growth.'],
        ['icon'=>'shield','title'=>'KYC & Compliance','desc'=>'Built-in verification workflow gating sensitive payouts.'],
        ['icon'=>'percent','title'=>'Performance Bonus','desc'=>'Monthly profit sharing via dynamic spend slabs & normalization.'],
        ['icon'=>'chart','title'=>'Actionable Insights','desc'=>'Admin dashboard with real-time referral, payout and fee reporting.'],
        ['icon'=>'secure','title'=>'Secure Flows','desc'=>'Nonces, rate limits (extensible) and modular architecture.'],
      ];
      foreach($features as $f): ?>
        <div class="feature-card">
          <div class="icon-badge">
            <?php if($f['icon']==='wallet'): ?>
              <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 7h18v10H3z"/><path d="M16 12h4"/></svg>
            <?php elseif($f['icon']==='referral'): ?>
              <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="7" cy="7" r="3"/><circle cx="17" cy="7" r="3"/><circle cx="12" cy="17" r="3"/><path d="M9.5 9.5l-2 5M14.5 9.5l2 5M9 7h6"/></svg>
            <?php elseif($f['icon']==='shield'): ?>
              <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 3l8 4v5c0 5-3.5 9-8 9s-8-4-8-9V7z"/></svg>
            <?php elseif($f['icon']==='percent'): ?>
              <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 5L5 19"/><circle cx="8.5" cy="8.5" r="2.5"/><circle cx="15.5" cy="15.5" r="2.5"/></svg>
            <?php elseif($f['icon']==='chart'): ?>
              <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 19h16"/><path d="M8 16V8"/><path d="M12 16V5"/><path d="M16 16v-6"/></svg>
            <?php else: ?>
              <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 2l7 4v6c0 5-3 9-7 10-4-1-7-5-7-10V6z"/></svg>
            <?php endif; ?>
          </div>
          <h3><?php echo esc_html($f['title']); ?></h3>
          <p><?php echo esc_html($f['desc']); ?></p>
        </div>
      <?php endforeach; ?>
    </section>
  </main>
  <footer class="footer">&copy; <?php echo date('Y'); ?> SVNTeX. All rights reserved.</footer>
</div>
<?php wp_footer(); ?>
</body>
</html>
