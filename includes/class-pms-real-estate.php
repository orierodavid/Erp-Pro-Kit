<?php
if (! defined('ABSPATH')) { exit; }

class PMS_Real_Estate
{
    private static function table(string $name): string { global $wpdb; return $wpdb->prefix . 'pms_re_' . $name; }

    public static function schema_sql(): string
    {
        $c = $GLOBALS['wpdb']->get_charset_collate();
        $t = fn($n) => self::table($n);
        return "
CREATE TABLE {$t('property_types')} (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(100) NOT NULL,
 description TEXT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 PRIMARY KEY (id), KEY status (status)
) {$c};
CREATE TABLE {$t('locations')} (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(191) NOT NULL,
 address TEXT NULL,
 city VARCHAR(100) NULL,
 state VARCHAR(100) NULL,
 country VARCHAR(100) NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 PRIMARY KEY (id), KEY city (city), KEY status (status)
) {$c};
CREATE TABLE {$t('owners')} (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(191) NOT NULL,
 email VARCHAR(191) NULL,
 phone VARCHAR(64) NULL,
 address TEXT NULL,
 notes TEXT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 PRIMARY KEY (id), KEY email (email), KEY status (status)
) {$c};
CREATE TABLE {$t('properties')} (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 property_code VARCHAR(50) NOT NULL,
 title VARCHAR(191) NOT NULL,
 property_type_id BIGINT UNSIGNED NULL,
 location_id BIGINT UNSIGNED NULL,
 owner_id BIGINT UNSIGNED NULL,
 bedrooms INT NOT NULL DEFAULT 0,
 bathrooms INT NOT NULL DEFAULT 0,
 area DECIMAL(14,2) NOT NULL DEFAULT 0,
 area_unit VARCHAR(20) NOT NULL DEFAULT 'sqm',
 status VARCHAR(30) NOT NULL DEFAULT 'available',
 description TEXT NULL,
 created_at DATETIME NULL,
 updated_at DATETIME NULL,
 PRIMARY KEY (id), UNIQUE KEY property_code (property_code), KEY property_type_id (property_type_id), KEY location_id (location_id), KEY owner_id (owner_id), KEY status (status)
) {$c};
CREATE TABLE {$t('listings')} (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 property_id BIGINT UNSIGNED NOT NULL,
 listing_type VARCHAR(20) NOT NULL DEFAULT 'sale',
 price DECIMAL(18,2) NOT NULL DEFAULT 0,
 currency VARCHAR(10) NOT NULL DEFAULT 'NGN',
 agent_id BIGINT UNSIGNED NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 listed_at DATETIME NULL,
 notes TEXT NULL,
 PRIMARY KEY (id), KEY property_id (property_id), KEY agent_id (agent_id), KEY status (status)
) {$c};
CREATE TABLE {$t('buyers')} (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(191) NOT NULL,
 email VARCHAR(191) NULL,
 phone VARCHAR(64) NULL,
 budget DECIMAL(18,2) NOT NULL DEFAULT 0,
 currency VARCHAR(10) NOT NULL DEFAULT 'NGN',
 agent_id BIGINT UNSIGNED NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 notes TEXT NULL,
 PRIMARY KEY (id), KEY agent_id (agent_id), KEY status (status)
) {$c};
CREATE TABLE {$t('tenants')} (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(191) NOT NULL,
 email VARCHAR(191) NULL,
 phone VARCHAR(64) NULL,
 agent_id BIGINT UNSIGNED NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 notes TEXT NULL,
 PRIMARY KEY (id), KEY agent_id (agent_id), KEY status (status)
) {$c};
CREATE TABLE {$t('viewings')} (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 property_id BIGINT UNSIGNED NOT NULL,
 contact_type VARCHAR(20) NOT NULL DEFAULT 'buyer',
 contact_id BIGINT UNSIGNED NULL,
 agent_id BIGINT UNSIGNED NULL,
 viewing_at DATETIME NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'scheduled',
 notes TEXT NULL,
 PRIMARY KEY (id), KEY property_id (property_id), KEY agent_id (agent_id), KEY viewing_at (viewing_at)
) {$c};
CREATE TABLE {$t('offers')} (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 property_id BIGINT UNSIGNED NOT NULL,
 buyer_id BIGINT UNSIGNED NOT NULL,
 amount DECIMAL(18,2) NOT NULL DEFAULT 0,
 currency VARCHAR(10) NOT NULL DEFAULT 'NGN',
 status VARCHAR(20) NOT NULL DEFAULT 'pending',
 offered_at DATETIME NULL,
 notes TEXT NULL,
 PRIMARY KEY (id), KEY property_id (property_id), KEY buyer_id (buyer_id), KEY status (status)
) {$c};
CREATE TABLE {$t('sales')} (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 property_id BIGINT UNSIGNED NOT NULL,
 buyer_id BIGINT UNSIGNED NULL,
 agent_id BIGINT UNSIGNED NULL,
 amount DECIMAL(18,2) NOT NULL DEFAULT 0,
 currency VARCHAR(10) NOT NULL DEFAULT 'NGN',
 sale_date DATE NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'completed',
 notes TEXT NULL,
 PRIMARY KEY (id), KEY property_id (property_id), KEY buyer_id (buyer_id), KEY agent_id (agent_id), KEY sale_date (sale_date)
) {$c};
CREATE TABLE {$t('rentals')} (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 property_id BIGINT UNSIGNED NOT NULL,
 tenant_id BIGINT UNSIGNED NOT NULL,
 agent_id BIGINT UNSIGNED NULL,
 rent_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
 currency VARCHAR(10) NOT NULL DEFAULT 'NGN',
 frequency VARCHAR(20) NOT NULL DEFAULT 'monthly',
 start_date DATE NULL,
 end_date DATE NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 notes TEXT NULL,
 PRIMARY KEY (id), KEY property_id (property_id), KEY tenant_id (tenant_id), KEY agent_id (agent_id), KEY status (status)
) {$c};
CREATE TABLE {$t('leases')} (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 property_id BIGINT UNSIGNED NOT NULL,
 tenant_id BIGINT UNSIGNED NOT NULL,
 rental_id BIGINT UNSIGNED NULL,
 lease_number VARCHAR(80) NOT NULL,
 start_date DATE NULL,
 end_date DATE NULL,
 rent_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
 currency VARCHAR(10) NOT NULL DEFAULT 'NGN',
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 notes TEXT NULL,
 PRIMARY KEY (id), UNIQUE KEY lease_number (lease_number), KEY property_id (property_id), KEY tenant_id (tenant_id), KEY status (status)
) {$c};
CREATE TABLE {$t('commissions')} (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 property_id BIGINT UNSIGNED NULL,
 sale_id BIGINT UNSIGNED NULL,
 rental_id BIGINT UNSIGNED NULL,
 agent_id BIGINT UNSIGNED NOT NULL,
 basis_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
 rate DECIMAL(7,2) NOT NULL DEFAULT 0,
 commission_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
 currency VARCHAR(10) NOT NULL DEFAULT 'NGN',
 status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
 due_date DATE NULL,
 paid_at DATETIME NULL,
 notes TEXT NULL,
 PRIMARY KEY (id), KEY agent_id (agent_id), KEY status (status), KEY sale_id (sale_id), KEY rental_id (rental_id)
) {$c};";
    }

    public static function table_name(string $name): string { return self::table($name); }

    public static function all(string $name): array
    {
        global $wpdb;
        $allowed = ['property_types','locations','owners','properties','listings','buyers','tenants','viewings','offers','sales','rentals','leases','commissions'];
        if (! in_array($name, $allowed, true)) return [];
        return $wpdb->get_results("SELECT * FROM ".self::table($name)." ORDER BY id DESC");
    }

    public static function properties(): array
    {
        global $wpdb;
        return $wpdb->get_results("SELECT p.*,pt.name property_type,l.name location_name,o.name owner_name FROM ".self::table('properties')." p
            LEFT JOIN ".self::table('property_types')." pt ON pt.id=p.property_type_id
            LEFT JOIN ".self::table('locations')." l ON l.id=p.location_id
            LEFT JOIN ".self::table('owners')." o ON o.id=p.owner_id ORDER BY p.id DESC");
    }

    public static function listings(): array
    {
        global $wpdb;
        return $wpdb->get_results("SELECT x.*,p.title property_title,p.property_code FROM ".self::table('listings')." x
            LEFT JOIN ".self::table('properties')." p ON p.id=x.property_id ORDER BY x.id DESC");
    }

    public static function viewings(): array
    {
        global $wpdb;
        return $wpdb->get_results("SELECT x.*,p.title property_title FROM ".self::table('viewings')." x
            LEFT JOIN ".self::table('properties')." p ON p.id=x.property_id ORDER BY x.viewing_at DESC,x.id DESC");
    }

    public static function offers(): array
    {
        global $wpdb;
        return $wpdb->get_results("SELECT x.*,p.title property_title,b.name buyer_name FROM ".self::table('offers')." x
            LEFT JOIN ".self::table('properties')." p ON p.id=x.property_id
            LEFT JOIN ".self::table('buyers')." b ON b.id=x.buyer_id ORDER BY x.id DESC");
    }

    public static function sales(): array
    {
        global $wpdb;
        return $wpdb->get_results("SELECT x.*,p.title property_title,b.name buyer_name FROM ".self::table('sales')." x
            LEFT JOIN ".self::table('properties')." p ON p.id=x.property_id
            LEFT JOIN ".self::table('buyers')." b ON b.id=x.buyer_id ORDER BY x.sale_date DESC,x.id DESC");
    }

    public static function rentals(): array
    {
        global $wpdb;
        return $wpdb->get_results("SELECT x.*,p.title property_title,t.name tenant_name FROM ".self::table('rentals')." x
            LEFT JOIN ".self::table('properties')." p ON p.id=x.property_id
            LEFT JOIN ".self::table('tenants')." t ON t.id=x.tenant_id ORDER BY x.id DESC");
    }

    public static function leases(): array
    {
        global $wpdb;
        return $wpdb->get_results("SELECT x.*,p.title property_title,t.name tenant_name FROM ".self::table('leases')." x
            LEFT JOIN ".self::table('properties')." p ON p.id=x.property_id
            LEFT JOIN ".self::table('tenants')." t ON t.id=x.tenant_id ORDER BY x.id DESC");
    }

    public static function commissions(): array
    {
        global $wpdb;
        return $wpdb->get_results("SELECT x.*,p.title property_title,u.display_name agent_name FROM ".self::table('commissions')." x
            LEFT JOIN ".self::table('properties')." p ON p.id=x.property_id
            LEFT JOIN {$wpdb->users} u ON u.ID=x.agent_id ORDER BY x.id DESC");
    }

    public static function summary(): object
    {
        global $wpdb; $r=new stdClass();
        foreach (['properties','listings','buyers','tenants','viewings','offers','sales','rentals','leases','commissions'] as $n) {
            $r->{$n}=(int)$wpdb->get_var("SELECT COUNT(*) FROM ".self::table($n));
        }
        $r->sales_value=(float)$wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM ".self::table('sales')." WHERE status='completed'");
        $r->commission_due=(float)$wpdb->get_var("SELECT COALESCE(SUM(commission_amount),0) FROM ".self::table('commissions')." WHERE status <> 'paid'");
        return $r;
    }

    public static function save(string $type, array $data): int
    {
        global $wpdb; $now=current_time('mysql');
        $schemas = [
            'property_types'=>['name','description','status'],
            'locations'=>['name','address','city','state','country','status'],
            'owners'=>['name','email','phone','address','notes','status'],
            'properties'=>['property_code','title','property_type_id','location_id','owner_id','bedrooms','bathrooms','area','area_unit','status','description'],
            'listings'=>['property_id','listing_type','price','currency','agent_id','status','listed_at','notes'],
            'buyers'=>['name','email','phone','budget','currency','agent_id','status','notes'],
            'tenants'=>['name','email','phone','agent_id','status','notes'],
            'viewings'=>['property_id','contact_type','contact_id','agent_id','viewing_at','status','notes'],
            'offers'=>['property_id','buyer_id','amount','currency','status','offered_at','notes'],
            'sales'=>['property_id','buyer_id','agent_id','amount','currency','sale_date','status','notes'],
            'rentals'=>['property_id','tenant_id','agent_id','rent_amount','currency','frequency','start_date','end_date','status','notes'],
            'leases'=>['property_id','tenant_id','rental_id','lease_number','start_date','end_date','rent_amount','currency','status','notes'],
            'commissions'=>['property_id','sale_id','rental_id','agent_id','basis_amount','rate','commission_amount','currency','status','due_date','paid_at','notes'],
        ];
        if (!isset($schemas[$type])) return 0;
        $row=[];
        foreach ($schemas[$type] as $key) {
            $v=$data[$key] ?? null;
            if (in_array($key,['property_type_id','location_id','owner_id','property_id','agent_id','contact_id','buyer_id','tenant_id','rental_id','sale_id'],true)) $v=$v ? (int)$v : null;
            if (in_array($key,['bedrooms','bathrooms'],true)) $v=max(0,(int)$v);
            if (in_array($key,['area','price','budget','amount','rent_amount','basis_amount','rate','commission_amount'],true)) $v=(float)$v;
            $row[$key]=$v;
        }
        if (in_array($type,['properties','listings','buyers','tenants','viewings','offers','sales','rentals','leases','commissions'],true) && empty($row['currency']) && array_key_exists('currency',$row)) $row['currency']='NGN';
        if ($type==='properties' && empty($row['property_code'])) $row['property_code']='PROP-'.strtoupper(wp_generate_password(7,false,false));
        if ($type==='leases' && empty($row['lease_number'])) $row['lease_number']='LEASE-'.strtoupper(wp_generate_password(8,false,false));
        if ($type==='commissions' && empty($row['commission_amount'])) $row['commission_amount']=round(((float)$row['basis_amount']*(float)$row['rate'])/100,2);
        if (array_key_exists('created_at',$row)) $row['created_at']=$now;
        if (in_array($type,['properties'],true)) { $row['created_at']=$now; $row['updated_at']=$now; }
        $wpdb->insert(self::table($type),$row);
        return (int)$wpdb->insert_id;
    }

    public static function delete(string $type,int $id): void
    {
        global $wpdb; $allowed=['property_types','locations','owners','properties','listings','buyers','tenants','viewings','offers','sales','rentals','leases','commissions'];
        if (in_array($type,$allowed,true)) $wpdb->delete(self::table($type),['id'=>$id],['%d']);
    }
}
