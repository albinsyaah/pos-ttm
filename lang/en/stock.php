<?php

return [
    'insufficient' => 'Insufficient stock for :product (:code) in warehouse :warehouse: available :available, requested :requested.',
    'insufficient_reverse' => 'This transaction cannot be cancelled or changed: :product (:code) in warehouse :warehouse has only :available in stock, but :requested must be taken back.',
    'insufficient_all' => 'Not enough stock for :product (:code) in any warehouse: :available in total, :requested requested.',
    'product_not_found' => 'Product #:id was not found.',
    'return_not_received' => 'Purchase :source has not been received, so nothing can be returned from it.',
    'return_product_not_on_source' => ':product (:code) is not on :source, so it cannot be returned.',
    'return_exceeds' => 'Cannot return :requested of :product (:code): :source has :original, :returned already returned, :remaining left.',
    'return_no_source' => 'Cannot put :product back into a warehouse: the sale has no record of taking it from one.',
];
