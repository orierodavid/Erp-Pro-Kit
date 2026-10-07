<?php
if (! defined('ABSPATH')) { exit; }

class PMS_Payroll
{
    public static function salary_table(): string { global $wpdb; return $wpdb->prefix . 'pms_payroll_salary_details'; }
    public static function allowances_table(): string { global $wpdb; return $wpdb->prefix . 'pms_payroll_allowances'; }
    public static function deductions_table(): string { global $wpdb; return $wpdb->prefix . 'pms_payroll_deductions'; }
    public static function runs_table(): string { global $wpdb; return $wpdb->prefix . 'pms_payroll_runs'; }
    public static function payslips_table(): string { global $wpdb; return $wpdb->prefix . 'pms_payroll_payslips'; }

    public static function schema_sql(): string
    {
        global $wpdb; $c=$wpdb->get_charset_collate();
        $s=self::salary_table(); $a=self::allowances_table(); $d=self::deductions_table(); $r=self::runs_table(); $p=self::payslips_table();
        return "
CREATE TABLE {$s} (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 user_id BIGINT UNSIGNED NOT NULL,
 basic_salary DECIMAL(18,2) NOT NULL DEFAULT 0,
 currency VARCHAR(10) NOT NULL,
 pay_frequency VARCHAR(20) NOT NULL DEFAULT 'monthly',
 effective_from DATE NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 notes TEXT NULL,
 created_at DATETIME NULL,
 updated_at DATETIME NULL,
 PRIMARY KEY  (id),
 UNIQUE KEY user_id (user_id),
 KEY status (status)
) {$c};
CREATE TABLE {$a} (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 user_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(191) NOT NULL,
 amount DECIMAL(18,2) NOT NULL DEFAULT 0,
 frequency VARCHAR(20) NOT NULL DEFAULT 'monthly',
 taxable TINYINT(1) NOT NULL DEFAULT 0,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 effective_from DATE NULL,
 notes TEXT NULL,
 created_at DATETIME NULL,
 updated_at DATETIME NULL,
 PRIMARY KEY  (id),
 KEY user_id (user_id),
 KEY status (status)
) {$c};
CREATE TABLE {$d} (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 user_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(191) NOT NULL,
 amount DECIMAL(18,2) NOT NULL DEFAULT 0,
 frequency VARCHAR(20) NOT NULL DEFAULT 'monthly',
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 effective_from DATE NULL,
 notes TEXT NULL,
 created_at DATETIME NULL,
 updated_at DATETIME NULL,
 PRIMARY KEY  (id),
 KEY user_id (user_id),
 KEY status (status)
) {$c};
CREATE TABLE {$r} (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(191) NOT NULL,
 period_start DATE NOT NULL,
 period_end DATE NOT NULL,
 pay_date DATE NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'draft',
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NULL,
 updated_at DATETIME NULL,
 PRIMARY KEY  (id),
 KEY period_start (period_start),
 KEY status (status)
) {$c};
CREATE TABLE {$p} (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 run_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 currency VARCHAR(10) NOT NULL,
 basic_salary DECIMAL(18,2) NOT NULL DEFAULT 0,
 allowances DECIMAL(18,2) NOT NULL DEFAULT 0,
 deductions DECIMAL(18,2) NOT NULL DEFAULT 0,
 gross_pay DECIMAL(18,2) NOT NULL DEFAULT 0,
 net_pay DECIMAL(18,2) NOT NULL DEFAULT 0,
 status VARCHAR(20) NOT NULL DEFAULT 'generated',
 created_at DATETIME NULL,
 updated_at DATETIME NULL,
 PRIMARY KEY  (id),
 UNIQUE KEY run_user (run_id, user_id),
 KEY run_id (run_id),
 KEY user_id (user_id)
) {$c};";
    }

    public static function employees(): array { return PMS_Roles::get_pms_people(); }

    public static function salary(int $user_id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::salary_table().' WHERE user_id=%d',$user_id)) ?: null;
    }

    public static function salaries(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT s.*,u.display_name,u.user_email FROM '.self::salary_table().' s LEFT JOIN '.$wpdb->users.' u ON u.ID=s.user_id ORDER BY u.display_name ASC');
    }

    public static function save_salary(int $user_id,array $data): bool
    {
        global $wpdb; $old=self::salary($user_id);
        $row=['user_id'=>$user_id,'basic_salary'=>(float)$data['basic_salary'],'currency'=>strtoupper(sanitize_text_field($data['currency'])),'pay_frequency'=>sanitize_key($data['pay_frequency']),'effective_from'=>$data['effective_from']?:null,'status'=>sanitize_key($data['status']),'notes'=>sanitize_textarea_field($data['notes']),'updated_at'=>current_time('mysql')];
        if($old) return false!==$wpdb->update(self::salary_table(),$row,['user_id'=>$user_id]);
        $row['created_at']=current_time('mysql'); return false!==$wpdb->insert(self::salary_table(),$row);
    }

    public static function components(string $type,?int $user_id=null): array
    {
        global $wpdb; $t=$type==='allowance'?self::allowances_table():self::deductions_table();
        $sql='SELECT c.*,u.display_name FROM '.$t.' c LEFT JOIN '.$wpdb->users.' u ON u.ID=c.user_id';
        if($user_id) $sql.=$wpdb->prepare(' WHERE c.user_id=%d',$user_id);
        return $wpdb->get_results($sql.' ORDER BY u.display_name ASC,c.name ASC');
    }

    public static function component(string $type,int $id): ?object
    {
        global $wpdb; $t=$type==='allowance'?self::allowances_table():self::deductions_table();
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.$t.' WHERE id=%d',$id)) ?: null;
    }

    public static function save_component(string $type,array $data,?int $id=null): bool
    {
        global $wpdb; $t=$type==='allowance'?self::allowances_table():self::deductions_table();
        $row=['user_id'=>absint($data['user_id']),'name'=>sanitize_text_field($data['name']),'amount'=>(float)$data['amount'],'frequency'=>sanitize_key($data['frequency']),'effective_from'=>$data['effective_from']?:null,'notes'=>sanitize_textarea_field($data['notes']),'status'=>sanitize_key($data['status']),'updated_at'=>current_time('mysql')];
        if($type==='allowance') $row['taxable']=!empty($data['taxable'])?1:0;
        if($id) return false!==$wpdb->update($t,$row,['id'=>$id]);
        $row['created_at']=current_time('mysql'); return false!==$wpdb->insert($t,$row);
    }

    public static function delete_component(string $type,int $id): bool
    {
        global $wpdb; $t=$type==='allowance'?self::allowances_table():self::deductions_table();
        return false!==$wpdb->delete($t,['id'=>$id]);
    }

    public static function runs(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT r.*,COUNT(p.id) payslip_count,COALESCE(SUM(p.net_pay),0) total_net FROM '.self::runs_table().' r LEFT JOIN '.self::payslips_table().' p ON p.run_id=r.id GROUP BY r.id ORDER BY r.period_start DESC,r.id DESC');
    }

    public static function run(int $id): ?object
    {
        global $wpdb; return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::runs_table().' WHERE id=%d',$id)) ?: null;
    }

    public static function create_run(array $data): int
    {
        global $wpdb;
        $wpdb->insert(self::runs_table(),['name'=>sanitize_text_field($data['name']),'period_start'=>$data['period_start'],'period_end'=>$data['period_end'],'pay_date'=>$data['pay_date']?:null,'status'=>'draft','created_by'=>get_current_user_id(),'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')]);
        return (int)$wpdb->insert_id;
    }

    public static function generate_payslips(int $run_id): int
    {
        global $wpdb; $run=self::run($run_id); if(!$run) return 0; $count=0;
        foreach(self::salaries() as $salary){
            if($salary->status!=='active') continue;
            $allowance_total=0; foreach(self::components('allowance',(int)$salary->user_id) as $x) if($x->status==='active'&&$x->frequency==='monthly') $allowance_total+=(float)$x->amount;
            $deduction_total=0; foreach(self::components('deduction',(int)$salary->user_id) as $x) if($x->status==='active'&&$x->frequency==='monthly') $deduction_total+=(float)$x->amount;
            $basic=(float)$salary->basic_salary; $gross=$basic+$allowance_total; $net=$gross-$deduction_total;
            $existing=$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::payslips_table().' WHERE run_id=%d AND user_id=%d',$run_id,$salary->user_id));
            $row=['run_id'=>$run_id,'user_id'=>(int)$salary->user_id,'currency'=>$salary->currency,'basic_salary'=>$basic,'allowances'=>$allowance_total,'deductions'=>$deduction_total,'gross_pay'=>$gross,'net_pay'=>$net,'status'=>'generated','updated_at'=>current_time('mysql')];
            if($existing) $wpdb->update(self::payslips_table(),$row,['id'=>(int)$existing]); else {$row['created_at']=current_time('mysql');$wpdb->insert(self::payslips_table(),$row);}
            $count++;
        }
        $wpdb->update(self::runs_table(),['status'=>'processed','updated_at'=>current_time('mysql')],['id'=>$run_id]); return $count;
    }

    public static function payslips(?int $run_id=null): array
    {
        global $wpdb;
        $sql='SELECT p.*,u.display_name,r.name run_name,r.period_start,r.period_end,r.pay_date FROM '.self::payslips_table().' p LEFT JOIN '.$wpdb->users.' u ON u.ID=p.user_id LEFT JOIN '.self::runs_table().' r ON r.id=p.run_id';
        if($run_id) $sql.=$wpdb->prepare(' WHERE p.run_id=%d',$run_id);
        return $wpdb->get_results($sql.' ORDER BY r.period_start DESC,u.display_name ASC');
    }

    public static function report_totals(): object
    {
        global $wpdb;
        return $wpdb->get_row('SELECT COUNT(*) payslips,COALESCE(SUM(gross_pay),0) gross,COALESCE(SUM(deductions),0) deductions,COALESCE(SUM(net_pay),0) net FROM '.self::payslips_table());
    }
}
