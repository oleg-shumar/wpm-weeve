<h2 class="user-transactions">Points transactions</h2>
<div class="under_table_transaction">
    <div class="header_transaction">
        <div>ID</div>
        <div>Method</div>
        <div>Date</div>
        <div>Points</div>
        <div>End Date</div>
    </div>
    <div class="body_transaction">
        <?php foreach($user_history as $row) { ?>
            <div class="item_transaction">
                <div><?= $row->id ?></div>
                <div><?php if($row->order_id == -2) {echo 'Discount';} elseif($row->order_id == -1) {echo 'Admin Give';} elseif($row->order_id == 0) {echo 'Voucher';} elseif($row->order_id > 0) {echo 'Order #'.$row->order_id;} ?></div>
                <div><?= date('d.m.Y', strtotime($row->date_start)) ?></div>
                <div><?= $row->points ?></div>
                <div><?= date('d.m.Y', strtotime($row->date_expire)) ?></div>
            </div>
        <?php } ?>
    </div>
</div>