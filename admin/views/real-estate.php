<?php
if (! defined('ABSPATH')) { exit; }
if (! current_user_can('pms_manage_real_estate')) { wp_die(__('You do not have permission to view this page.', 'pms')); }
wp_enqueue_media();

$tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'overview';
$allowed_tabs = ['overview','properties','listings','contacts','transactions','leases','commissions','communications','reports'];
if (! in_array($tab,$allowed_tabs,true)) $tab='overview';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    check_admin_referer('pms_real_estate_manage','pms_real_estate_nonce');
    $action=sanitize_key(wp_unslash($_POST['pms_re_action'] ?? ''));
    $redirect_tab=sanitize_key(wp_unslash($_POST['redirect_tab'] ?? $tab));
    $allowed=['property_types','locations','owners','properties','listings','buyers','tenants','viewings','offers','sales','rentals','leases','commissions'];
    if (strpos($action,'save_')===0) {
        $type=substr($action,5);
        if (in_array($type,$allowed,true)) {
            $data=[];
            foreach ($_POST as $key=>$value) {
                if (strpos($key,'pms_')===0 || $key==='redirect_tab') continue;
                $data[sanitize_key($key)] = is_array($value) ? '' : sanitize_textarea_field(wp_unslash($value));
            }
            PMS_Real_Estate::save($type,$data);
            echo '<div class="pms-notice pms-notice-success">'.esc_html__('Saved successfully.','pms').'</div>';
        }
    } elseif ($action==='delete') {
        $type=sanitize_key(wp_unslash($_POST['entity_type'] ?? ''));
        $id=absint($_POST['entity_id'] ?? 0);
        if ($id) PMS_Real_Estate::delete($type,$id);
        echo '<div class="pms-notice pms-notice-success">'.esc_html__('Record deleted.','pms').'</div>';
    } elseif ($action==='send_email') {
        $recipient_type=sanitize_key(wp_unslash($_POST['recipient_type'] ?? ''));
        $recipient_id=absint($_POST['recipient_id'] ?? 0);
        $to='';
        if ($recipient_type==='tenant' && $recipient_id) {
            global $wpdb;
            $recipient=$wpdb->get_row($wpdb->prepare("SELECT email FROM ".PMS_Real_Estate::table_name('tenants')." WHERE id=%d",$recipient_id));
            $to=$recipient ? sanitize_email($recipient->email) : '';
        } elseif ($recipient_type==='staff' && $recipient_id) {
            $user=get_userdata($recipient_id);
            $to=$user ? sanitize_email($user->user_email) : '';
        } elseif ($recipient_type==='custom') {
            $to=sanitize_email(wp_unslash($_POST['recipient_email'] ?? ''));
        }
        $sent=PMS_Real_Estate::send_email($to, wp_unslash($_POST['subject'] ?? ''), wp_unslash($_POST['message'] ?? ''));
        echo $sent ? '<div class="pms-notice pms-notice-success">Email submitted to the WordPress mail transport.</div>' : '<div class="pms-notice pms-notice-error">Email could not be submitted. Check the recipient, WordPress mail configuration, and server/SMTP transport.</div>';
    }
}

$summary=PMS_Real_Estate::summary();
$types=PMS_Real_Estate::all('property_types');
$locations=PMS_Real_Estate::all('locations');
$owners=PMS_Real_Estate::all('owners');
$properties=PMS_Real_Estate::properties();
$listings=PMS_Real_Estate::listings();
$buyers=PMS_Real_Estate::all('buyers');
$tenants=PMS_Real_Estate::all('tenants');
$viewings=PMS_Real_Estate::viewings();
$offers=PMS_Real_Estate::offers();
$sales=PMS_Real_Estate::sales();
$rentals=PMS_Real_Estate::rentals();
$leases=PMS_Real_Estate::leases();
$commissions=PMS_Real_Estate::commissions();
$agents=PMS_Roles::get_pms_people();

function pms_re_tabs(string $active): void {
    $tabs=['overview'=>['Overview','dashicons-dashboard'],'properties'=>['Properties','dashicons-building'],'listings'=>['Listings','dashicons-admin-home'],'contacts'=>['Buyers & Tenants','dashicons-groups'],'transactions'=>['Viewings & Transactions','dashicons-calendar-alt'],'leases'=>['Rentals & Leases','dashicons-media-document'],'commissions'=>['Commissions','dashicons-money-alt'],'reports'=>['Property Reports','dashicons-chart-bar']];
    foreach($tabs as $slug=>$meta) {
        printf('<a class="%s" href="%s"><span class="dashicons %s"></span>%s</a>',
            $active===$slug?'is-active':'',
            esc_url(admin_url('admin.php?page=pms-real-estate&tab='.$slug)),
            esc_attr($meta[1]),esc_html($meta[0]));
    }
}
function pms_re_select(string $name,array $rows,string $label): void {
    echo '<div class="pms-form-row"><label>'.esc_html($label).'</label><select class="pms-input" name="'.esc_attr($name).'"><option value="">Select</option>';
    foreach($rows as $row) echo '<option value="'.esc_attr($row->id).'">'.esc_html($row->name ?? $row->title ?? ('#'.$row->id)).'</option>';
    echo '</select></div>';
}
?>
<div class="pms-wrap pms-real-estate">
    <div class="pms-page-header">
        <div><p class="pms-eyebrow"><?php esc_html_e('REAL ESTATE','pms'); ?></p><h1><?php esc_html_e('Real Estate','pms'); ?></h1><p class="pms-page-description"><?php esc_html_e('Manage properties, listings, buyers, tenants, transactions, leases and agent commissions in one workspace.','pms'); ?></p></div>
        <a class="pms-btn-primary" href="<?php echo esc_url(admin_url('admin.php?page=pms-real-estate&tab=properties')); ?>"><span class="dashicons dashicons-plus-alt2"></span><?php esc_html_e('Add property','pms'); ?></a>
    </div>

    <div class="pms-real-estate-tabs"><?php pms_re_tabs($tab); ?></div>

    <?php if($tab==='overview'): ?>
        <div class="pms-kpi-grid pms-real-estate-kpis">
            <div class="pms-kpi"><span class="pms-kpi-label">Properties</span><strong><?php echo esc_html($summary->properties); ?></strong></div>
            <div class="pms-kpi"><span class="pms-kpi-label">Active listings</span><strong><?php echo esc_html($summary->listings); ?></strong></div>
            <div class="pms-kpi"><span class="pms-kpi-label">Buyers</span><strong><?php echo esc_html($summary->buyers); ?></strong></div>
            <div class="pms-kpi"><span class="pms-kpi-label">Tenants</span><strong><?php echo esc_html($summary->tenants); ?></strong></div>
            <div class="pms-kpi"><span class="pms-kpi-label">Sales value</span><strong><?php echo esc_html(number_format($summary->sales_value,2)); ?></strong></div>
            <div class="pms-kpi"><span class="pms-kpi-label">Commission due</span><strong><?php echo esc_html(number_format($summary->commission_due,2)); ?></strong></div>
        </div>

        <div class="pms-real-estate-grid">
            <div class="pms-panel pms-form-panel">
                <div class="pms-section-head"><div><h2>Property setup</h2><p>Maintain property types, locations and owners.</p></div></div>
                <form method="post"><?php wp_nonce_field('pms_real_estate_manage','pms_real_estate_nonce'); ?><input type="hidden" name="pms_re_action" value="save_property_types"><input type="hidden" name="redirect_tab" value="overview"><div class="pms-form-grid"><div class="pms-form-row"><label>Property type</label><input class="pms-input" name="name" required></div><div class="pms-form-row"><label>Status</label><select class="pms-input" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div></div><div class="pms-form-row"><label>Description</label><textarea class="pms-input" name="description" rows="2"></textarea></div><button class="pms-btn-primary" type="submit">Add property type</button></form>
                <div class="pms-real-estate-mini-list"><?php foreach($types as $x): ?><span><?php echo esc_html($x->name); ?></span><?php endforeach; if(!$types): ?><em>No property types yet.</em><?php endif; ?></div>
            </div>
            <div class="pms-panel pms-form-panel">
                <div class="pms-section-head"><div><h2>Locations</h2><p>Keep property locations reusable.</p></div></div>
                <form method="post"><?php wp_nonce_field('pms_real_estate_manage','pms_real_estate_nonce'); ?><input type="hidden" name="pms_re_action" value="save_locations"><input type="hidden" name="redirect_tab" value="overview"><div class="pms-form-grid"><div class="pms-form-row"><label>Location name</label><input class="pms-input" name="name" required></div><div class="pms-form-row"><label>City</label><input class="pms-input" name="city"></div><div class="pms-form-row"><label>State</label><input class="pms-input" name="state"></div><div class="pms-form-row"><label>Country</label><input class="pms-input" name="country"></div></div><div class="pms-form-row"><label>Address</label><textarea class="pms-input" name="address" rows="2"></textarea></div><button class="pms-btn-primary" type="submit">Add location</button></form>
                <div class="pms-real-estate-mini-list"><?php foreach($locations as $x): ?><span><?php echo esc_html($x->name); ?></span><?php endforeach; if(!$locations): ?><em>No locations yet.</em><?php endif; ?></div>
            </div>
            <div class="pms-panel pms-form-panel">
                <div class="pms-section-head"><div><h2>Property owners</h2><p>Owner records for properties.</p></div></div>
                <form method="post"><?php wp_nonce_field('pms_real_estate_manage','pms_real_estate_nonce'); ?><input type="hidden" name="pms_re_action" value="save_owners"><input type="hidden" name="redirect_tab" value="overview"><div class="pms-form-grid"><div class="pms-form-row"><label>Name</label><input class="pms-input" name="name" required></div><div class="pms-form-row"><label>Email</label><input class="pms-input" type="email" name="email"></div><div class="pms-form-row"><label>Phone</label><input class="pms-input" name="phone"></div><div class="pms-form-row"><label>Status</label><select class="pms-input" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div></div><div class="pms-form-row"><label>Address / Notes</label><textarea class="pms-input" name="notes" rows="2"></textarea></div><button class="pms-btn-primary" type="submit">Add owner</button></form>
            </div>
        </div>
    <?php endif; ?>

    <?php if($tab==='properties'): ?>
        <div class="pms-panel pms-form-panel">
            <div class="pms-section-head"><div><h2>Add property</h2><p>Property master records are the foundation for listings and transactions.</p></div></div>
            <form method="post"><?php wp_nonce_field('pms_real_estate_manage','pms_real_estate_nonce'); ?><input type="hidden" name="pms_re_action" value="save_properties"><input type="hidden" name="redirect_tab" value="properties">
                <div class="pms-form-grid"><div class="pms-form-row"><label>Property code</label><input class="pms-input" name="property_code" placeholder="Auto-generated if blank"></div><div class="pms-form-row"><label>Property title</label><input class="pms-input" name="title" required></div><div class="pms-form-row pms-re-image-field"><label>Property image</label><input type="hidden" name="featured_image_id" id="pms-re-featured-image-id"><div class="pms-re-image-picker"><div id="pms-re-image-preview" class="pms-re-image-preview"><span class="dashicons dashicons-format-image"></span><span>No image selected</span></div><button type="button" class="pms-btn" id="pms-re-select-image">Choose image</button><button type="button" class="pms-btn pms-btn-danger" id="pms-re-remove-image" style="display:none">Remove</button></div></div><?php pms_re_select('property_type_id',$types,'Property type'); ?><?php pms_re_select('location_id',$locations,'Location'); ?><?php pms_re_select('owner_id',$owners,'Property owner'); ?><div class="pms-form-row"><label>Status</label><select class="pms-input" name="status"><option value="available">Available</option><option value="reserved">Reserved</option><option value="sold">Sold</option><option value="rented">Rented</option><option value="inactive">Inactive</option></select></div><div class="pms-form-row"><label>Bedrooms</label><input class="pms-input" type="number" min="0" name="bedrooms" value="0"></div><div class="pms-form-row"><label>Bathrooms</label><input class="pms-input" type="number" min="0" name="bathrooms" value="0"></div><div class="pms-form-row"><label>Area</label><input class="pms-input" type="number" min="0" step="0.01" name="area" value="0"></div><div class="pms-form-row"><label>Area unit</label><input class="pms-input" name="area_unit" value="sqm"></div></div>
                <div class="pms-form-row"><label>Description</label><textarea class="pms-input" name="description" rows="3"></textarea></div><button class="pms-btn-primary" type="submit">Save property</button>
            </form>
        </div>
        <div class="pms-panel"><div class="pms-panel-heading"><h2>Properties</h2><span><?php echo esc_html(count($properties)); ?> records</span></div><table class="pms-table"><thead><tr><th>Image</th><th>Code</th><th>Property</th><th>Type</th><th>Location</th><th>Owner</th><th>Details</th><th>Status</th></tr></thead><tbody><?php foreach($properties as $p): ?><tr><td><?php if(!empty($p->featured_image_id)): ?><img class="pms-re-table-thumb" src="<?php echo esc_url(wp_get_attachment_image_url((int)$p->featured_image_id,'thumbnail')); ?>" alt=""><?php else: ?><span class="pms-re-table-placeholder"><span class="dashicons dashicons-format-image"></span></span><?php endif; ?></td><td><strong><?php echo esc_html($p->property_code); ?></strong></td><td><?php echo esc_html($p->title); ?></td><td><?php echo esc_html($p->property_type?:'—'); ?></td><td><?php echo esc_html($p->location_name?:'—'); ?></td><td><?php echo esc_html($p->owner_name?:'—'); ?></td><td><?php echo esc_html($p->bedrooms.' bd · '.$p->bathrooms.' ba · '.$p->area.' '.$p->area_unit); ?></td><td><span class="pms-chip"><?php echo esc_html(ucfirst($p->status)); ?></span></td></tr><?php endforeach; if(!$properties): ?><tr><td colspan="8" class="pms-empty">No properties yet.</td></tr><?php endif; ?></tbody></table></div>
    <?php endif; ?>

    <?php if($tab==='listings'): ?>
        <div class="pms-panel pms-form-panel"><div class="pms-section-head"><div><h2>Property listings</h2><p>Publish a property for sale or rent and assign an agent.</p></div></div>
        <form method="post"><?php wp_nonce_field('pms_real_estate_manage','pms_real_estate_nonce'); ?><input type="hidden" name="pms_re_action" value="save_listings"><input type="hidden" name="redirect_tab" value="listings"><div class="pms-form-grid"><?php pms_re_select('property_id',$properties,'Property'); ?><div class="pms-form-row"><label>Listing type</label><select class="pms-input" name="listing_type"><option value="sale">Sale</option><option value="rent">Rent</option></select></div><div class="pms-form-row"><label>Price</label><input class="pms-input" type="number" min="0" step="0.01" name="price" value="0"></div><div class="pms-form-row"><label>Currency</label><input class="pms-input" name="currency" value="NGN"></div><div class="pms-form-row"><label>Agent</label><select class="pms-input" name="agent_id"><option value="">Unassigned</option><?php foreach($agents as $a): ?><option value="<?php echo esc_attr($a->ID); ?>"><?php echo esc_html($a->display_name); ?></option><?php endforeach; ?></select></div><div class="pms-form-row"><label>Status</label><select class="pms-input" name="status"><option value="active">Active</option><option value="paused">Paused</option><option value="closed">Closed</option></select></div><div class="pms-form-row"><label>Listed at</label><input class="pms-input" type="datetime-local" name="listed_at"></div></div><div class="pms-form-row"><label>Notes</label><textarea class="pms-input" name="notes" rows="2"></textarea></div><button class="pms-btn-primary" type="submit">Create listing</button></form></div>
        <div class="pms-panel"><div class="pms-panel-heading"><h2>Listings</h2></div><div class="pms-re-listing-grid"><?php foreach($listings as $x): ?><article class="pms-re-listing-card"><div class="pms-re-listing-image"><?php $img_id=0; foreach($properties as $p) { if((int)$p->id===(int)$x->property_id) { $img_id=(int)$p->featured_image_id; break; } } if($img_id): ?><img src="<?php echo esc_url(wp_get_attachment_image_url($img_id,'medium')); ?>" alt="<?php echo esc_attr($x->property_title); ?>"><?php else: ?><span class="dashicons dashicons-building"></span><?php endif; ?><span class="pms-re-listing-type"><?php echo esc_html(ucfirst($x->listing_type)); ?></span></div><div class="pms-re-listing-body"><div><span class="pms-re-listing-code"><?php echo esc_html($x->property_code); ?></span><h3><?php echo esc_html($x->property_title); ?></h3></div><div class="pms-re-listing-price"><?php echo esc_html($x->currency); ?> <?php echo esc_html(number_format((float)$x->price,2)); ?></div><div class="pms-re-listing-meta"><span><?php echo esc_html(ucfirst($x->status)); ?></span><span><?php echo esc_html($x->listed_at?:'Not dated'); ?></span></div></div></article><?php endforeach; if(!$listings): ?><div class="pms-empty">No listings yet.</div><?php endif; ?></div></div>
    <?php endif; ?>

    <?php if($tab==='contacts'): ?>
        <div class="pms-real-estate-grid">
            <div class="pms-panel pms-form-panel"><div class="pms-section-head"><div><h2>Buyers</h2><p>Buyer records and budgets.</p></div></div><form method="post"><?php wp_nonce_field('pms_real_estate_manage','pms_real_estate_nonce'); ?><input type="hidden" name="pms_re_action" value="save_buyers"><input type="hidden" name="redirect_tab" value="contacts"><div class="pms-form-grid"><div class="pms-form-row"><label>Name</label><input class="pms-input" name="name" required></div><div class="pms-form-row"><label>Email</label><input class="pms-input" type="email" name="email"></div><div class="pms-form-row"><label>Phone</label><input class="pms-input" name="phone"></div><div class="pms-form-row"><label>Budget</label><input class="pms-input" type="number" min="0" step="0.01" name="budget" value="0"></div><div class="pms-form-row"><label>Currency</label><input class="pms-input" name="currency" value="NGN"></div><div class="pms-form-row"><label>Agent</label><select class="pms-input" name="agent_id"><option value="">Unassigned</option><?php foreach($agents as $a): ?><option value="<?php echo esc_attr($a->ID); ?>"><?php echo esc_html($a->display_name); ?></option><?php endforeach; ?></select></div></div><button class="pms-btn-primary" type="submit">Add buyer</button></form><table class="pms-table"><thead><tr><th>Name</th><th>Contact</th><th>Budget</th></tr></thead><tbody><?php foreach($buyers as $x): ?><tr><td><?php echo esc_html($x->name); ?></td><td><?php echo esc_html($x->email?:$x->phone?:'—'); ?></td><td><?php echo esc_html($x->currency.' '.number_format((float)$x->budget,2)); ?></td></tr><?php endforeach; if(!$buyers): ?><tr><td colspan="3" class="pms-empty">No buyers yet.</td></tr><?php endif; ?></tbody></table></div>
            <div class="pms-panel pms-form-panel"><div class="pms-section-head"><div><h2>Tenants</h2><p>Tenant records for rentals and leases.</p></div></div><form method="post"><?php wp_nonce_field('pms_real_estate_manage','pms_real_estate_nonce'); ?><input type="hidden" name="pms_re_action" value="save_tenants"><input type="hidden" name="redirect_tab" value="contacts"><div class="pms-form-grid"><div class="pms-form-row"><label>Name</label><input class="pms-input" name="name" required></div><div class="pms-form-row"><label>Email</label><input class="pms-input" type="email" name="email"></div><div class="pms-form-row"><label>Phone</label><input class="pms-input" name="phone"></div><div class="pms-form-row"><label>Agent</label><select class="pms-input" name="agent_id"><option value="">Unassigned</option><?php foreach($agents as $a): ?><option value="<?php echo esc_attr($a->ID); ?>"><?php echo esc_html($a->display_name); ?></option><?php endforeach; ?></select></div></div><button class="pms-btn-primary" type="submit">Add tenant</button></form><table class="pms-table"><thead><tr><th>Name</th><th>Contact</th><th>Status</th></tr></thead><tbody><?php foreach($tenants as $x): ?><tr><td><?php echo esc_html($x->name); ?></td><td><?php echo esc_html($x->email?:$x->phone?:'—'); ?></td><td><?php echo esc_html(ucfirst($x->status)); ?></td></tr><?php endforeach; if(!$tenants): ?><tr><td colspan="3" class="pms-empty">No tenants yet.</td></tr><?php endif; ?></tbody></table></div>
        </div>
    <?php endif; ?>

    <?php if($tab==='transactions'): ?>
        <div class="pms-real-estate-grid">
            <div class="pms-panel pms-form-panel"><h2>Viewings</h2><form method="post"><?php wp_nonce_field('pms_real_estate_manage','pms_real_estate_nonce'); ?><input type="hidden" name="pms_re_action" value="save_viewings"><input type="hidden" name="redirect_tab" value="transactions"><div class="pms-form-grid"><?php pms_re_select('property_id',$properties,'Property'); ?><div class="pms-form-row"><label>Contact type</label><select class="pms-input" name="contact_type"><option value="buyer">Buyer</option><option value="tenant">Tenant</option></select></div><div class="pms-form-row"><label>Contact ID</label><input class="pms-input" type="number" min="1" name="contact_id"></div><div class="pms-form-row"><label>Agent</label><select class="pms-input" name="agent_id"><option value="">Unassigned</option><?php foreach($agents as $a): ?><option value="<?php echo esc_attr($a->ID); ?>"><?php echo esc_html($a->display_name); ?></option><?php endforeach; ?></select></div><div class="pms-form-row"><label>Viewing date/time</label><input class="pms-input" type="datetime-local" name="viewing_at"></div><div class="pms-form-row"><label>Status</label><select class="pms-input" name="status"><option value="scheduled">Scheduled</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></div></div><button class="pms-btn-primary" type="submit">Schedule viewing</button></form><table class="pms-table"><tbody><?php foreach($viewings as $x): ?><tr><td><?php echo esc_html($x->property_title); ?></td><td><?php echo esc_html($x->viewing_at?:'—'); ?></td><td><?php echo esc_html(ucfirst($x->status)); ?></td></tr><?php endforeach; if(!$viewings): ?><tr><td class="pms-empty">No viewings yet.</td></tr><?php endif; ?></tbody></table></div>
            <div class="pms-panel pms-form-panel"><h2>Offers</h2><form method="post"><?php wp_nonce_field('pms_real_estate_manage','pms_real_estate_nonce'); ?><input type="hidden" name="pms_re_action" value="save_offers"><input type="hidden" name="redirect_tab" value="transactions"><div class="pms-form-grid"><?php pms_re_select('property_id',$properties,'Property'); ?><?php pms_re_select('buyer_id',$buyers,'Buyer'); ?><div class="pms-form-row"><label>Amount</label><input class="pms-input" type="number" min="0" step="0.01" name="amount" required></div><div class="pms-form-row"><label>Currency</label><input class="pms-input" name="currency" value="NGN"></div><div class="pms-form-row"><label>Status</label><select class="pms-input" name="status"><option value="pending">Pending</option><option value="accepted">Accepted</option><option value="rejected">Rejected</option></select></div><div class="pms-form-row"><label>Offered at</label><input class="pms-input" type="datetime-local" name="offered_at"></div></div><button class="pms-btn-primary" type="submit">Record offer</button></form><table class="pms-table"><tbody><?php foreach($offers as $x): ?><tr><td><?php echo esc_html($x->property_title); ?></td><td><?php echo esc_html($x->buyer_name); ?></td><td><?php echo esc_html($x->currency.' '.number_format((float)$x->amount,2)); ?></td><td><?php echo esc_html(ucfirst($x->status)); ?></td></tr><?php endforeach; if(!$offers): ?><tr><td class="pms-empty">No offers yet.</td></tr><?php endif; ?></tbody></table></div>
            <div class="pms-panel pms-form-panel"><h2>Sales</h2><form method="post"><?php wp_nonce_field('pms_real_estate_manage','pms_real_estate_nonce'); ?><input type="hidden" name="pms_re_action" value="save_sales"><input type="hidden" name="redirect_tab" value="transactions"><div class="pms-form-grid"><?php pms_re_select('property_id',$properties,'Property'); ?><?php pms_re_select('buyer_id',$buyers,'Buyer'); ?><div class="pms-form-row"><label>Agent</label><select class="pms-input" name="agent_id"><option value="">Unassigned</option><?php foreach($agents as $a): ?><option value="<?php echo esc_attr($a->ID); ?>"><?php echo esc_html($a->display_name); ?></option><?php endforeach; ?></select></div><div class="pms-form-row"><label>Sale amount</label><input class="pms-input" type="number" min="0" step="0.01" name="amount" required></div><div class="pms-form-row"><label>Currency</label><input class="pms-input" name="currency" value="NGN"></div><div class="pms-form-row"><label>Sale date</label><input class="pms-input" type="date" name="sale_date"></div></div><button class="pms-btn-primary" type="submit">Record sale</button></form><table class="pms-table"><tbody><?php foreach($sales as $x): ?><tr><td><?php echo esc_html($x->property_title); ?></td><td><?php echo esc_html($x->buyer_name?:'—'); ?></td><td><?php echo esc_html($x->currency.' '.number_format((float)$x->amount,2)); ?></td><td><?php echo esc_html($x->sale_date?:'—'); ?></td></tr><?php endforeach; if(!$sales): ?><tr><td class="pms-empty">No sales yet.</td></tr><?php endif; ?></tbody></table></div>
        </div>
    <?php endif; ?>

    <?php if($tab==='leases'): ?>
        <div class="pms-real-estate-grid">
            <div class="pms-panel pms-form-panel"><h2>Rentals</h2><form method="post"><?php wp_nonce_field('pms_real_estate_manage','pms_real_estate_nonce'); ?><input type="hidden" name="pms_re_action" value="save_rentals"><input type="hidden" name="redirect_tab" value="leases"><div class="pms-form-grid"><?php pms_re_select('property_id',$properties,'Property'); ?><?php pms_re_select('tenant_id',$tenants,'Tenant'); ?><div class="pms-form-row"><label>Agent</label><select class="pms-input" name="agent_id"><option value="">Unassigned</option><?php foreach($agents as $a): ?><option value="<?php echo esc_attr($a->ID); ?>"><?php echo esc_html($a->display_name); ?></option><?php endforeach; ?></select></div><div class="pms-form-row"><label>Rent amount</label><input class="pms-input" type="number" min="0" step="0.01" name="rent_amount" required></div><div class="pms-form-row"><label>Currency</label><input class="pms-input" name="currency" value="NGN"></div><div class="pms-form-row"><label>Frequency</label><select class="pms-input" name="frequency"><option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="yearly">Yearly</option></select></div><div class="pms-form-row"><label>Start date</label><input class="pms-input" type="date" name="start_date"></div><div class="pms-form-row"><label>End date</label><input class="pms-input" type="date" name="end_date"></div></div><button class="pms-btn-primary" type="submit">Create rental</button></form><table class="pms-table"><tbody><?php foreach($rentals as $x): ?><tr><td><?php echo esc_html($x->property_title); ?></td><td><?php echo esc_html($x->tenant_name); ?></td><td><?php echo esc_html($x->currency.' '.number_format((float)$x->rent_amount,2).' / '.$x->frequency); ?></td><td><?php echo esc_html(ucfirst($x->status)); ?></td></tr><?php endforeach; if(!$rentals): ?><tr><td class="pms-empty">No rentals yet.</td></tr><?php endif; ?></tbody></table></div>
            <div class="pms-panel pms-form-panel"><h2>Leases</h2><form method="post"><?php wp_nonce_field('pms_real_estate_manage','pms_real_estate_nonce'); ?><input type="hidden" name="pms_re_action" value="save_leases"><input type="hidden" name="redirect_tab" value="leases"><div class="pms-form-grid"><?php pms_re_select('property_id',$properties,'Property'); ?><?php pms_re_select('tenant_id',$tenants,'Tenant'); ?><div class="pms-form-row"><label>Rental ID</label><input class="pms-input" type="number" min="1" name="rental_id"></div><div class="pms-form-row"><label>Lease number</label><input class="pms-input" name="lease_number" placeholder="Auto-generated if blank"></div><div class="pms-form-row"><label>Start date</label><input class="pms-input" type="date" name="start_date"></div><div class="pms-form-row"><label>End date</label><input class="pms-input" type="date" name="end_date"></div><div class="pms-form-row"><label>Rent amount</label><input class="pms-input" type="number" min="0" step="0.01" name="rent_amount" required></div><div class="pms-form-row"><label>Currency</label><input class="pms-input" name="currency" value="NGN"></div><div class="pms-form-row"><label>Status</label><select class="pms-input" name="status"><option value="active">Active</option><option value="expired">Expired</option><option value="terminated">Terminated</option></select></div></div><button class="pms-btn-primary" type="submit">Create lease</button></form><table class="pms-table"><tbody><?php foreach($leases as $x): ?><tr><td><?php echo esc_html($x->lease_number); ?></td><td><?php echo esc_html($x->property_title); ?></td><td><?php echo esc_html($x->tenant_name); ?></td><td><?php echo esc_html($x->start_date.' — '.$x->end_date); ?></td><td><?php echo esc_html(ucfirst($x->status)); ?></td></tr><?php endforeach; if(!$leases): ?><tr><td class="pms-empty">No leases yet.</td></tr><?php endif; ?></tbody></table></div>
        </div>
    <?php endif; ?>

    <?php if($tab==='commissions'): ?>
        <div class="pms-panel pms-form-panel"><div class="pms-section-head"><div><h2>Agent commissions</h2><p>Track commission basis, rate, payable amount and payment status.</p></div></div><form method="post"><?php wp_nonce_field('pms_real_estate_manage','pms_real_estate_nonce'); ?><input type="hidden" name="pms_re_action" value="save_commissions"><input type="hidden" name="redirect_tab" value="commissions"><div class="pms-form-grid"><?php pms_re_select('property_id',$properties,'Property'); ?><div class="pms-form-row"><label>Agent</label><select class="pms-input" name="agent_id" required><option value="">Select agent</option><?php foreach($agents as $a): ?><option value="<?php echo esc_attr($a->ID); ?>"><?php echo esc_html($a->display_name); ?></option><?php endforeach; ?></select></div><div class="pms-form-row"><label>Sale ID</label><input class="pms-input" type="number" min="1" name="sale_id"></div><div class="pms-form-row"><label>Rental ID</label><input class="pms-input" type="number" min="1" name="rental_id"></div><div class="pms-form-row"><label>Basis amount</label><input class="pms-input" type="number" min="0" step="0.01" name="basis_amount" required></div><div class="pms-form-row"><label>Rate (%)</label><input class="pms-input" type="number" min="0" step="0.01" name="rate" required></div><div class="pms-form-row"><label>Commission amount</label><input class="pms-input" type="number" min="0" step="0.01" name="commission_amount" placeholder="Auto-calculated"></div><div class="pms-form-row"><label>Currency</label><input class="pms-input" name="currency" value="NGN"></div><div class="pms-form-row"><label>Status</label><select class="pms-input" name="status"><option value="unpaid">Unpaid</option><option value="paid">Paid</option><option value="disputed">Disputed</option></select></div><div class="pms-form-row"><label>Due date</label><input class="pms-input" type="date" name="due_date"></div></div><button class="pms-btn-primary" type="submit">Record commission</button></form></div>
        <div class="pms-panel"><table class="pms-table"><thead><tr><th>Agent</th><th>Property</th><th>Basis</th><th>Rate</th><th>Commission</th><th>Status</th></tr></thead><tbody><?php foreach($commissions as $x): ?><tr><td><?php echo esc_html($x->agent_name?:'—'); ?></td><td><?php echo esc_html($x->property_title?:'—'); ?></td><td><?php echo esc_html($x->currency.' '.number_format((float)$x->basis_amount,2)); ?></td><td><?php echo esc_html(number_format((float)$x->rate,2).'%'); ?></td><td><?php echo esc_html($x->currency.' '.number_format((float)$x->commission_amount,2)); ?></td><td><?php echo esc_html(ucfirst($x->status)); ?></td></tr><?php endforeach; if(!$commissions): ?><tr><td colspan="6" class="pms-empty">No commissions yet.</td></tr><?php endif; ?></tbody></table></div>
    <?php endif; ?>

    <?php if($tab==='communications'): ?>
        <div class="pms-real-estate-grid">
            <div class="pms-panel pms-form-panel">
                <div class="pms-section-head"><div><h2>Send communication</h2><p>Send a direct email to a tenant, staff member, or another verified address.</p></div></div>
                <form method="post">
                    <?php wp_nonce_field('pms_real_estate_manage','pms_real_estate_nonce'); ?>
                    <input type="hidden" name="pms_re_action" value="send_email"><input type="hidden" name="redirect_tab" value="communications">
                    <div class="pms-form-grid">
                        <div class="pms-form-row"><label>Recipient type</label><select class="pms-input" name="recipient_type" id="pms-re-recipient-type"><option value="tenant">Tenant</option><option value="staff">Staff</option><option value="custom">Email address</option></select></div>
                        <div class="pms-form-row" id="pms-re-recipient-select"><label>Recipient</label><select class="pms-input" name="recipient_id"><option value="">Select recipient</option><?php foreach($tenants as $x): if(!empty($x->email)): ?><option data-type="tenant" value="<?php echo esc_attr($x->id); ?>"><?php echo esc_html($x->name.' — '.$x->email); ?></option><?php endif; endforeach; ?><?php foreach($agents as $a): if(!empty($a->user_email)): ?><option data-type="staff" value="<?php echo esc_attr($a->ID); ?>"><?php echo esc_html($a->display_name.' — '.$a->user_email); ?></option><?php endif; endforeach; ?></select></div>
                        <div class="pms-form-row" id="pms-re-custom-email" style="display:none"><label>Email address</label><input class="pms-input" type="email" name="recipient_email" placeholder="name@example.com"></div>
                        <div class="pms-form-row"><label>Subject</label><input class="pms-input" name="subject" required></div>
                    </div>
                    <div class="pms-form-row"><label>Message</label><textarea class="pms-input" name="message" rows="8" required></textarea></div>
                    <button class="pms-btn-primary" type="submit"><span class="dashicons dashicons-email-alt"></span> Send email</button>
                </form>
            </div>
            <div class="pms-panel pms-form-panel">
                <div class="pms-section-head"><div><h2>Email delivery</h2><p>WordPress hands mail to the site's configured mail transport.</p></div></div>
                <div class="pms-re-email-check"><span class="dashicons dashicons-admin-email"></span><div><strong>Site email</strong><p><?php echo esc_html(get_option('admin_email')); ?></p></div></div>
                <p class="pms-page-description">For production delivery, configure a reliable SMTP or transactional mail service on the WordPress site. The ERP sends through <code>wp_mail()</code>.</p>
                <a class="pms-btn" href="<?php echo esc_url(admin_url('options-general.php')); ?>">Open WordPress settings</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if($tab==='reports'): ?>
        <div class="pms-kpi-grid"><div class="pms-kpi"><span class="pms-kpi-label">Viewings</span><strong><?php echo esc_html($summary->viewings); ?></strong></div><div class="pms-kpi"><span class="pms-kpi-label">Offers</span><strong><?php echo esc_html($summary->offers); ?></strong></div><div class="pms-kpi"><span class="pms-kpi-label">Sales</span><strong><?php echo esc_html($summary->sales); ?></strong></div><div class="pms-kpi"><span class="pms-kpi-label">Rentals</span><strong><?php echo esc_html($summary->rentals); ?></strong></div><div class="pms-kpi"><span class="pms-kpi-label">Leases</span><strong><?php echo esc_html($summary->leases); ?></strong></div><div class="pms-kpi"><span class="pms-kpi-label">Commissions</span><strong><?php echo esc_html($summary->commissions); ?></strong></div></div>
        <div class="pms-panel"><div class="pms-panel-heading"><h2>Property reports</h2><span>Live totals from stored real-estate records.</span></div><table class="pms-table"><tbody><tr><th>Properties</th><td><?php echo esc_html($summary->properties); ?></td></tr><tr><th>Listings</th><td><?php echo esc_html($summary->listings); ?></td></tr><tr><th>Buyers</th><td><?php echo esc_html($summary->buyers); ?></td></tr><tr><th>Tenants</th><td><?php echo esc_html($summary->tenants); ?></td></tr><tr><th>Viewings</th><td><?php echo esc_html($summary->viewings); ?></td></tr><tr><th>Offers</th><td><?php echo esc_html($summary->offers); ?></td></tr><tr><th>Completed sales value</th><td><?php echo esc_html(number_format($summary->sales_value,2)); ?></td></tr><tr><th>Commission outstanding</th><td><?php echo esc_html(number_format($summary->commission_due,2)); ?></td></tr></tbody></table></div>
    <?php endif; ?>
</div>
<script>
jQuery(function($){
 var frame;
 $('#pms-re-select-image').on('click',function(e){e.preventDefault();if(frame){frame.open();return;}frame=wp.media({title:'Select property image',button:{text:'Use image'},multiple:false,library:{type:'image'}});frame.on('select',function(){var a=frame.state().get('selection').first().toJSON();$('#pms-re-featured-image-id').val(a.id);var u=(a.sizes&&a.sizes.medium)?a.sizes.medium.url:(a.url||'');$('#pms-re-image-preview').html(u?'<img src="'+u.replace(/"/g,'&quot;')+'" alt=""><span>Selected image</span>':'<span class="dashicons dashicons-format-image"></span><span>No image selected</span>');$('#pms-re-remove-image').show();});frame.open();});
 $('#pms-re-remove-image').on('click',function(){$('#pms-re-featured-image-id').val('');$('#pms-re-image-preview').html('<span class="dashicons dashicons-format-image"></span><span>No image selected</span>');$(this).hide();});
 $('#pms-re-recipient-type').on('change',function(){var t=$(this).val();$('#pms-re-recipient-select').toggle(t!=='custom');$('#pms-re-custom-email').toggle(t==='custom');$('#pms-re-recipient-select option[data-type]').each(function(){$(this).toggle($(this).data('type')===t);});$('#pms-re-recipient-select select').val('');}).trigger('change');
});
</script>
<?php include PMS_PLUGIN_DIR.'admin/views/partials/footer.php'; ?>
