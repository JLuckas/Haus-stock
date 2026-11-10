<?php

return [

    'currency' => 'BRL',

    /*
    | Margin (profit ÷ selling price) below which a product is flagged "below_threshold".
    | 65% margin = 35% food cost (CMV), a common target for burger shops.
    */
    'healthy_margin_percent' => (float) env('PROFITABILITY_HEALTHY_MARGIN', 65),

];
