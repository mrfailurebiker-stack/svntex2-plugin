<?php
// Slab assignment and bonus display shortcode
function svntex2_slab_bonus_dashboard() {
    ob_start();
    ?>
    <link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__); ?>svntex2-ui.css">
    <div class="svntex2-card">
        <div class="svntex2-title">Slab & Bonus Dashboard</div>
        <div class="slab-info">
            <div style="margin-bottom:12px;">
                <span class="material-icons" style="vertical-align:middle;color:#ff9800;">star_rate</span>
                <strong>Current Slab:</strong> <span style="color:#0077cc;">10%</span>
            </div>
            <div class="progress-bar" style="background:#eee;border-radius:8px;overflow:hidden;height:18px;">
                <div class="progress" style="background:#0077cc;height:18px;width:10%;border-radius:8px;"></div>
            </div>
            <div style="margin-top:12px;">
                <strong>PB (Partnership Bonus):</strong> ₹0.00<br>
                <strong>RB (Referral Bonus):</strong> ₹0.00
            </div>
        </div>
        <div class="slab-table" style="margin-top:24px;">
            <h3>Slab Table</h3>
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="background:#f5fafd;color:#0077cc;">
                        <th>Amount (₹)</th>
                        <th>Slab %</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>2,499</td><td>10%</td><td>Minimum qualifying slab</td></tr>
                    <tr><td>3,499</td><td>15%</td><td>Moderate spend</td></tr>
                    <tr><td>4,499</td><td>20%</td><td>Elevated spend</td></tr>
                    <tr><td>5,499</td><td>25%</td><td>Mid-tier</td></tr>
                    <tr><td>6,499</td><td>30%</td><td>Higher tier</td></tr>
                    <tr><td>7,499</td><td>35%</td><td>Increasing tier</td></tr>
                    <tr><td>8,499</td><td>40%</td><td>Mid-high tier</td></tr>
                    <tr><td>9,499</td><td>45%</td><td>Mid-high tier</td></tr>
                    <tr><td>10,499</td><td>50%</td><td>Half company profit share</td></tr>
                    <tr><td>11,499</td><td>55%</td><td>Above half</td></tr>
                    <tr><td>12,499</td><td>60%</td><td>Premium tier</td></tr>
                    <tr><td>13,499</td><td>62%</td><td>Premium tier</td></tr>
                    <tr><td>14,499</td><td>65%</td><td>Premium tier</td></tr>
                    <tr><td>15,499</td><td>67%</td><td>Premium tier</td></tr>
                    <tr><td>16,499</td><td>68%</td><td>Near maximum</td></tr>
                    <tr><td>17,499</td><td>69%</td><td>Near maximum</td></tr>
                    <tr><td>19,999+</td><td>70%</td><td>Maximum slab allowed</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('svntex2_slab_bonus', 'svntex2_slab_bonus_dashboard');
