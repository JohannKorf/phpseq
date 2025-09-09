<?php
namespace Checkout;
use Payments\Gateway;
use Inventory\Stock;
class CartService { public function checkout(){ $g=new Gateway();$g->charge();$s=new Stock();$s->reserve(); } }
