<?php
if (! defined('ABSPATH')) { exit; }

class PMS_Inventory
{
    public static function items_table(): string { global $wpdb; return $wpdb->prefix . 'pms_inventory_items'; }
    public static function suppliers_table(): string { global $wpdb; return $wpdb->prefix . 'pms_inventory_suppliers'; }
    public static function movements_table(): string { global $wpdb; return $wpdb->prefix . 'pms_inventory_movements'; }

    public static function schema_sql(): string
    {
        global $wpdb; $c=$wpdb->get_charset_collate(); $i=self::items_table(); $s=self::suppliers_table(); $m=self::movements_table();
        return "CREATE TABLE {$s} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(190) NOT NULL, contact_name VARCHAR(190) NULL,
            email VARCHAR(190) NULL, phone VARCHAR(60) NULL, address TEXT NULL, status VARCHAR(24) NOT NULL DEFAULT 'active',
            created_at DATETIME NULL, updated_at DATETIME NULL, PRIMARY KEY(id), KEY status(status)
        ) {$c};
        CREATE TABLE {$i} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, sku VARCHAR(80) NOT NULL, name VARCHAR(190) NOT NULL,
            description TEXT NULL, category VARCHAR(100) NULL, supplier_id BIGINT UNSIGNED NULL, unit VARCHAR(40) NOT NULL DEFAULT 'unit',
            cost DECIMAL(18,2) NOT NULL DEFAULT 0, price DECIMAL(18,2) NOT NULL DEFAULT 0, stock DECIMAL(18,3) NOT NULL DEFAULT 0,
            reorder_level DECIMAL(18,3) NOT NULL DEFAULT 0, status VARCHAR(24) NOT NULL DEFAULT 'active',
            created_at DATETIME NULL, updated_at DATETIME NULL, PRIMARY KEY(id), UNIQUE KEY sku(sku), KEY supplier_id(supplier_id), KEY category(category), KEY status(status)
        ) {$c};
        CREATE TABLE {$m} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, item_id BIGINT UNSIGNED NOT NULL, movement_type VARCHAR(24) NOT NULL,
            quantity DECIMAL(18,3) NOT NULL, unit_cost DECIMAL(18,2) NOT NULL DEFAULT 0, reference VARCHAR(190) NULL,
            notes TEXT NULL, created_by BIGINT UNSIGNED NULL, created_at DATETIME NULL,
            PRIMARY KEY(id), KEY item_id(item_id), KEY movement_type(movement_type), KEY created_at(created_at)
        ) {$c};";
    }

    public static function items(): array
    {
        global $wpdb; $i=self::items_table(); $s=self::suppliers_table();
        return $wpdb->get_results("SELECT i.*, s.name supplier_name FROM {$i} i LEFT JOIN {$s} s ON s.id=i.supplier_id ORDER BY i.name ASC");
    }
    public static function suppliers(): array { global $wpdb; return $wpdb->get_results("SELECT * FROM ".self::suppliers_table()." ORDER BY name ASC"); }
    public static function summary(): array
    {
        global $wpdb; $i=self::items_table(); $s=self::suppliers_table();
        $x=$wpdb->get_row("SELECT COUNT(*) items,COALESCE(SUM(stock*cost),0) stock_value,COALESCE(SUM(CASE WHEN stock<=reorder_level THEN 1 ELSE 0 END),0) low_stock FROM {$i} WHERE status='active'");
        return ['items'=>(int)$x->items,'stock_value'=>(float)$x->stock_value,'low_stock'=>(int)$x->low_stock,'suppliers'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$s} WHERE status='active'")];
    }
    public static function create_supplier(array $d): int
    {
        global $wpdb; $ok=$wpdb->insert(self::suppliers_table(),[
            'name'=>sanitize_text_field($d['name']??''),'contact_name'=>sanitize_text_field($d['contact_name']??''),
            'email'=>sanitize_email($d['email']??''),'phone'=>sanitize_text_field($d['phone']??''),'address'=>sanitize_textarea_field($d['address']??''),
            'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')
        ],['%s','%s','%s','%s','%s','%s','%s']); return $ok?(int)$wpdb->insert_id:0;
    }
    public static function create_item(array $d): int
    {
        global $wpdb; $name=trim((string)($d['name']??'')); if($name==='')return 0;
        $sku=trim((string)($d['sku']??'')); if($sku==='')$sku='SKU-'.strtoupper(wp_generate_password(8,false,false));
        $ok=$wpdb->insert(self::items_table(),[
            'sku'=>sanitize_text_field($sku),'name'=>sanitize_text_field($name),'description'=>sanitize_textarea_field($d['description']??''),
            'category'=>sanitize_text_field($d['category']??''),'supplier_id'=>!empty($d['supplier_id'])?absint($d['supplier_id']):null,
            'unit'=>sanitize_text_field($d['unit']??'unit'),'cost'=>max(0,(float)($d['cost']??0)),'price'=>max(0,(float)($d['price']??0)),
            'stock'=>max(0,(float)($d['stock']??0)),'reorder_level'=>max(0,(float)($d['reorder_level']??0)),
            'status'=>'active','created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')
        ],['%s','%s','%s','%s','%d','%s','%f','%f','%f','%f','%s','%s','%s']); 
        if(!$ok)return 0; $id=(int)$wpdb->insert_id; $qty=max(0,(float)($d['stock']??0)); if($qty>0)self::movement($id,'opening',$qty,(float)($d['cost']??0),'Opening stock',''); return $id;
    }
    public static function movement(int $item_id,string $type,float $qty,float $unit_cost=0,string $reference='',string $notes=''): bool
    {
        global $wpdb; $item=$wpdb->get_row($wpdb->prepare("SELECT * FROM ".self::items_table()." WHERE id=%d",$item_id)); if(!$item||$qty<=0)return false;
        $delta=in_array($type,['in','opening','adjustment_in'],true)?$qty:-$qty; $new=(float)$item->stock+$delta; if($new<0)return false;
        $ok=$wpdb->insert(self::movements_table(),['item_id'=>$item_id,'movement_type'=>sanitize_key($type),'quantity'=>$qty,'unit_cost'=>max(0,$unit_cost),'reference'=>sanitize_text_field($reference),'notes'=>sanitize_textarea_field($notes),'created_by'=>get_current_user_id(),'created_at'=>current_time('mysql')],['%d','%s','%f','%f','%s','%s','%d','%s']);
        if(!$ok)return false; return false!==$wpdb->update(self::items_table(),['stock'=>$new,'updated_at'=>current_time('mysql')],['id'=>$item_id],['%f','%s'],['%d']);
    }
    public static function recent_movements(): array
    {
        global $wpdb; $m=self::movements_table(); $i=self::items_table();
        return $wpdb->get_results("SELECT m.*,i.name item_name,i.sku FROM {$m} m INNER JOIN {$i} i ON i.id=m.item_id ORDER BY m.id DESC LIMIT 50");
    }
}