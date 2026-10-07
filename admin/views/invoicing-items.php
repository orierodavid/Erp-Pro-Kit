<div class="pms-invoice-items">
    <div class="pms-invoice-items-head"><strong><?php esc_html_e('Line Items', 'pms'); ?></strong><button type="button" class="pms-btn pms-btn-small" data-add-invoice-item><span class="dashicons dashicons-plus-alt2"></span><?php esc_html_e('Add item', 'pms'); ?></button></div>
    <div class="pms-invoice-item-list" data-invoice-items>
        <div class="pms-invoice-item">
            <input class="pms-input" name="items[0][description]" placeholder="Description" required>
            <input class="pms-input" type="number" step="0.01" min="0" name="items[0][quantity]" value="1" placeholder="Qty" required>
            <input class="pms-input" type="number" step="0.01" min="0" name="items[0][unit_price]" value="0" placeholder="Unit price" required>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('[data-add-invoice-item]').forEach(function(button){
        button.addEventListener('click',function(){
            var list=button.closest('.pms-invoice-items').querySelector('[data-invoice-items]');
            var index=list.querySelectorAll('.pms-invoice-item').length;
            var row=document.createElement('div');
            row.className='pms-invoice-item';
            row.innerHTML='<input class="pms-input" name="items['+index+'][description]" placeholder="Description" required><input class="pms-input" type="number" step="0.01" min="0" name="items['+index+'][quantity]" value="1" placeholder="Qty" required><input class="pms-input" type="number" step="0.01" min="0" name="items['+index+'][unit_price]" value="0" placeholder="Unit price" required><button type="button" class="pms-btn pms-btn-small pms-btn-danger" aria-label="Remove item">×</button>';
            row.querySelector('button').addEventListener('click',function(){row.remove();});
            list.appendChild(row);
        });
    });
});
</script>