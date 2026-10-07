<?php
if (! defined('ABSPATH')) { exit; }

class PMS_CRM
{
    public static function leads_table(): string { global $wpdb; return $wpdb->prefix . 'pms_crm_leads'; }
    public static function prospects_table(): string { global $wpdb; return $wpdb->prefix . 'pms_crm_prospects'; }
    public static function customers_table(): string { global $wpdb; return $wpdb->prefix . 'pms_crm_customers'; }
    public static function stages_table(): string { global $wpdb; return $wpdb->prefix . 'pms_crm_pipeline_stages'; }
    public static function deals_table(): string { global $wpdb; return $wpdb->prefix . 'pms_crm_deals'; }
    public static function activities_table(): string { global $wpdb; return $wpdb->prefix . 'pms_crm_activities'; }

    public static function schema_sql(): string
    {
        $c = $GLOBALS['wpdb']->get_charset_collate();
        $l=self::leads_table(); $p=self::prospects_table(); $cu=self::customers_table(); $s=self::stages_table(); $d=self::deals_table(); $a=self::activities_table();
        return "
CREATE TABLE {$l} (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 first_name VARCHAR(100) NOT NULL,
 last_name VARCHAR(100) NULL,
 company VARCHAR(191) NULL,
 email VARCHAR(191) NULL,
 phone VARCHAR(64) NULL,
 source VARCHAR(100) NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'new',
 assigned_to BIGINT UNSIGNED NULL,
 notes TEXT NULL,
 created_at DATETIME NULL,
 updated_at DATETIME NULL,
 PRIMARY KEY  (id),
 KEY status (status),
 KEY assigned_to (assigned_to),
 KEY email (email)
) {$c};
CREATE TABLE {$p} (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 lead_id BIGINT UNSIGNED NULL,
 first_name VARCHAR(100) NOT NULL,
 last_name VARCHAR(100) NULL,
 company VARCHAR(191) NULL,
 email VARCHAR(191) NULL,
 phone VARCHAR(64) NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'qualified',
 assigned_to BIGINT UNSIGNED NULL,
 notes TEXT NULL,
 created_at DATETIME NULL,
 updated_at DATETIME NULL,
 PRIMARY KEY  (id),
 KEY lead_id (lead_id),
 KEY status (status),
 KEY assigned_to (assigned_to)
) {$c};
CREATE TABLE {$cu} (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 prospect_id BIGINT UNSIGNED NULL,
 name VARCHAR(191) NOT NULL,
 company VARCHAR(191) NULL,
 email VARCHAR(191) NULL,
 phone VARCHAR(64) NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'active',
 assigned_to BIGINT UNSIGNED NULL,
 notes TEXT NULL,
 created_at DATETIME NULL,
 updated_at DATETIME NULL,
 PRIMARY KEY  (id),
 KEY prospect_id (prospect_id),
 KEY status (status),
 KEY assigned_to (assigned_to)
) {$c};
CREATE TABLE {$s} (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(100) NOT NULL,
 sort_order INT NOT NULL DEFAULT 0,
 probability DECIMAL(5,2) NOT NULL DEFAULT 0,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 PRIMARY KEY  (id),
 KEY sort_order (sort_order),
 KEY status (status)
) {$c};
CREATE TABLE {$d} (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 title VARCHAR(191) NOT NULL,
 customer_id BIGINT UNSIGNED NULL,
 prospect_id BIGINT UNSIGNED NULL,
 stage_id BIGINT UNSIGNED NOT NULL,
 amount DECIMAL(18,2) NOT NULL DEFAULT 0,
 currency VARCHAR(10) NOT NULL,
 expected_close DATE NULL,
 assigned_to BIGINT UNSIGNED NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'open',
 notes TEXT NULL,
 created_at DATETIME NULL,
 updated_at DATETIME NULL,
 PRIMARY KEY  (id),
 KEY stage_id (stage_id),
 KEY customer_id (customer_id),
 KEY prospect_id (prospect_id),
 KEY assigned_to (assigned_to),
 KEY status (status)
) {$c};
CREATE TABLE {$a} (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 subject VARCHAR(191) NOT NULL,
 type VARCHAR(32) NOT NULL DEFAULT 'follow_up',
 due_at DATETIME NULL,
 completed_at DATETIME NULL,
 assigned_to BIGINT UNSIGNED NULL,
 lead_id BIGINT UNSIGNED NULL,
 prospect_id BIGINT UNSIGNED NULL,
 customer_id BIGINT UNSIGNED NULL,
 deal_id BIGINT UNSIGNED NULL,
 notes TEXT NULL,
 created_at DATETIME NULL,
 updated_at DATETIME NULL,
 PRIMARY KEY  (id),
 KEY due_at (due_at),
 KEY assigned_to (assigned_to),
 KEY lead_id (lead_id),
 KEY prospect_id (prospect_id),
 KEY customer_id (customer_id),
 KEY deal_id (deal_id)
) {$c};";
    }

    public static function people(): array { return PMS_Roles::get_pms_people(); }

    private static function table(string $type): string
    {
        return match ($type) {
            'lead' => self::leads_table(),
            'prospect' => self::prospects_table(),
            'customer' => self::customers_table(),
            'deal' => self::deals_table(),
            'activity' => self::activities_table(),
            default => self::leads_table(),
        };
    }

    public static function leads(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT l.*,u.display_name AS assignee_name FROM '.self::leads_table().' l LEFT JOIN '.$wpdb->users.' u ON u.ID=l.assigned_to ORDER BY l.created_at DESC,l.id DESC');
    }
    public static function prospects(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT p.*,u.display_name AS assignee_name FROM '.self::prospects_table().' p LEFT JOIN '.$wpdb->users.' u ON u.ID=p.assigned_to ORDER BY p.created_at DESC,p.id DESC');
    }
    public static function customers(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT c.*,u.display_name AS assignee_name FROM '.self::customers_table().' c LEFT JOIN '.$wpdb->users.' u ON u.ID=c.assigned_to ORDER BY c.created_at DESC,c.id DESC');
    }
    public static function stages(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT * FROM '.self::stages_table().' ORDER BY sort_order ASC,id ASC');
    }
    public static function deals(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT d.*,s.name stage_name,c.name customer_name,p.first_name prospect_first_name,p.last_name prospect_last_name,u.display_name AS assignee_name FROM '.self::deals_table().' d LEFT JOIN '.self::stages_table().' s ON s.id=d.stage_id LEFT JOIN '.self::customers_table().' c ON c.id=d.customer_id LEFT JOIN '.self::prospects_table().' p ON p.id=d.prospect_id LEFT JOIN '.$wpdb->users.' u ON u.ID=d.assigned_to ORDER BY s.sort_order ASC,d.created_at DESC');
    }
    public static function activities(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT a.*,u.display_name AS assignee_name,c.name customer_name,d.title deal_title FROM '.self::activities_table().' a LEFT JOIN '.$wpdb->users.' u ON u.ID=a.assigned_to LEFT JOIN '.self::customers_table().' c ON c.id=a.customer_id LEFT JOIN '.self::deals_table().' d ON d.id=a.deal_id ORDER BY a.due_at IS NULL ASC,a.due_at ASC,a.created_at DESC');
    }

    public static function save(string $type,array $data,?int $id=null): int
    {
        global $wpdb; $t=self::table($type);
        $now=current_time('mysql');
        if($type==='lead'||$type==='prospect'){
            $row=['first_name'=>$data['first_name'],'last_name'=>$data['last_name'],'company'=>$data['company'],'email'=>$data['email'],'phone'=>$data['phone'],'status'=>$data['status'],'assigned_to'=>$data['assigned_to']?:null,'notes'=>$data['notes'],'updated_at'=>$now];
            if($type==='lead') $row['source']=$data['source'];
        } elseif($type==='customer'){
            $row=['prospect_id'=>$data['prospect_id']?:null,'name'=>$data['name'],'company'=>$data['company'],'email'=>$data['email'],'phone'=>$data['phone'],'status'=>$data['status'],'assigned_to'=>$data['assigned_to']?:null,'notes'=>$data['notes'],'updated_at'=>$now];
        } elseif($type==='deal'){
            $row=['title'=>$data['title'],'customer_id'=>$data['customer_id']?:null,'prospect_id'=>$data['prospect_id']?:null,'stage_id'=>(int)$data['stage_id'],'amount'=>(float)$data['amount'],'currency'=>strtoupper($data['currency']),'expected_close'=>$data['expected_close']?:null,'assigned_to'=>$data['assigned_to']?:null,'status'=>$data['status'],'notes'=>$data['notes'],'updated_at'=>$now];
        } else {
            $row=['subject'=>$data['subject'],'type'=>$data['type'],'due_at'=>$data['due_at']?:null,'completed_at'=>$data['completed_at']?:null,'assigned_to'=>$data['assigned_to']?:null,'lead_id'=>$data['lead_id']?:null,'prospect_id'=>$data['prospect_id']?:null,'customer_id'=>$data['customer_id']?:null,'deal_id'=>$data['deal_id']?:null,'notes'=>$data['notes'],'updated_at'=>$now];
        }
        if($id){$wpdb->update($t,$row,['id'=>$id]);return $id;}
        $row['created_at']=$now;$wpdb->insert($t,$row);return (int)$wpdb->insert_id;
    }

    public static function delete(string $type,int $id): void { global $wpdb; $wpdb->delete(self::table($type),['id'=>$id]); }

    public static function convert_lead(int $lead_id): int
    {
        global $wpdb; $lead=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::leads_table().' WHERE id=%d',$lead_id));
        if(!$lead) return 0;
        $wpdb->insert(self::prospects_table(),['lead_id'=>$lead->id,'first_name'=>$lead->first_name,'last_name'=>$lead->last_name,'company'=>$lead->company,'email'=>$lead->email,'phone'=>$lead->phone,'status'=>'qualified','assigned_to'=>$lead->assigned_to?:null,'notes'=>$lead->notes,'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')]);
        $id=(int)$wpdb->insert_id;
        $wpdb->update(self::leads_table(),['status'=>'converted','updated_at'=>current_time('mysql')],['id'=>$lead_id]);
        return $id;
    }

    public static function report(): object
    {
        global $wpdb;
        $r=new stdClass();
        $r->leads=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.self::leads_table());
        $r->prospects=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.self::prospects_table());
        $r->customers=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.self::customers_table());
        $r->open_deals=(int)$wpdb->get_var("SELECT COUNT(*) FROM ".self::deals_table()." WHERE status='open'");
        $r->pipeline=(float)$wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM ".self::deals_table()." WHERE status='open'");
        $r->activities=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.self::activities_table());
        return $r;
    }
}
