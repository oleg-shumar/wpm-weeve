<style>
    .discounts_tables {
        padding: 20px;
    }

    .discounts_tables .head_discounts, .item-discounts {
        display: flex;
        flex-wrap: nowrap;
    }

    .discounts_tables .item-discounts div, .head_discounts div {
        width: 200px;
        margin-right: 10px;
        display: flex;
        align-items: center;
        justify-content: left;
    }

    .discounts_tables div.special-table {
        width: 200px;
    }

    .discounts_tables input {
        width: 100% !important;
    }

    .discounts_tables input {
        height: 10px;
    }

    .discounts_tables div.number_element {
        width: 20px;
    }

    .head_discounts {
        margin-bottom: 10px;
    }
    .title-discounts {
        font-weight: bold;
        margin-bottom: 20px;
        text-transform: uppercase;
    }

    .discounts_tables {
        border-bottom: 1px solid #ddd;
    }
    .item-discounts {
        margin-bottom: 15px;
    }
    .delete_item {
        color: red;
        cursor: pointer;
    }
    #save-settings {
        margin: 20px;
        padding: 5px 50px;
    }
    #discounts_tab_content {background: #fff;margin-top: 30px;margin-right: 20px;}
</style>
<div id="discounts_ranks_tab_content" class="panel woocommerce_options_panel hidden">
    <div class="discounts_tables">
        <div class="title-discounts">Discounts for product by user levels in %</div>
        <div class="head_discounts">
            <? foreach ($settings['rank'] as $setting) { ?>
                <div class="item-table"><?= $setting ?></div>
            <? } ?>
        </div>
        <div class="body_discounts more-discount">
            <div class="item-discounts">
                <? foreach ($settings['rank'] as $number => $setting) { ?>
                    <div class="item-table"><input type="number" name="wpm_rewards_product_discount[<?= $number ?>]" value="<? if(isset($product_discounts[$number])) {echo $product_discounts[$number];} else {echo 0;} ?>"></div>
                <? } ?>
            </div>
        </div>
    </div>
    <div class="discounts_tables special">
        <div class="title-discounts">Override all discount and prices</div>
        <div class="head_discounts">
            <div class="item-table">New price for all levels</div>
        </div>
        <div class="body_discounts">
            <div class="item-discounts">
                <div class="special-table"><input type="number" name="wpm_rewards_product_discount[global]" value="<? if(isset($product_discounts['global'])) {echo $product_discounts['global'];} else {echo 0;} ?>"></div>
            </div>
        </div>
    </div>
</div>