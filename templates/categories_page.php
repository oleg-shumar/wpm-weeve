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

<form action="" method="post">
    <div id="discounts_tab_content" class="panel woocommerce_options_panel">
        <div class="discounts_tables">
            <div class="title-discounts">How much points to give per category per level, %</div>
            <div class="head_discounts">
                <div class="number_element">#</div>
                <div class="item-table">Category</div>
                <? foreach ($settings['rank'] as $setting) { ?>
                    <div class="item-table"><?= $setting ?></div>
                <? } ?>
            </div>
            <div class="body_discounts more-discount">
                <? if(isset($product_categories) && count($product_categories) > 0) { $i = 1; foreach ($product_categories as $item => $category) { ?>
                    <div class="item-discounts">
                        <div class="number_element"><?= $i ?></div>
                        <div class="item-table"><?= $category->name ?></div>
                        <? foreach ($settings['rank'] as $number => $setting) { ?>
                            <div class="item-table"><input type="number" step="0.5" name="wpm_points_categories[<?= $category->term_id ?>][<?= $number ?>]" value="<?php if(isset($categories[$category->term_id][$number])) {echo $categories[$category->term_id][$number];} else {echo 0;} ?>"></div>
                        <? } ?>
                    </div>
                    <? $i++; }} else { ?>
                    <div class="item-discounts">
                        <div class="item-table">No categories is found</div>
                    </div>
                <? } ?>
            </div>
        </div>
        <div class="discounts_tables special" style="display: none">
            <div class="title-discounts">Override settings for all Categories % discounts</div>
            <div class="head_discounts">
                <? foreach ($settings['rank'] as $setting) { ?>
                    <div class="item-table"><?= $setting ?></div>
                <? } ?>
            </div>
            <div class="body_discounts">
                <div class="item-discounts">
                    <? foreach ($settings['rank'] as $number => $setting) { ?>
                        <div class="special-table"><input type="number" step="0.5" name="wpm_points_categories[global][<?= $number ?>]" value="<?php if(isset($categories['global'][$number])) {echo $categories['global'][$number];} else {echo 0;} ?>"></div>
                    <? } ?>
                </div>
            </div>
        </div>
        <button class="button button-primary button-large" id="save-settings" type="submit">Save settings</button>
    </div>
</form>