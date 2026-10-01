<?php
declare(strict_types=1);
namespace RouteBox\Integrations\Payment;
use PDO;
use RuntimeException;
final class OrderService
{
 public function __construct(private readonly PDO $db) {}
 public function create(int $userId,int $categoryId,int $planId,int $amount,string $currency='IRR'): int { $now=time();$s=$this->db->prepare('INSERT INTO orders(telegram_user_id,category_id,plan_id,subtotal_minor,total_minor,currency,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?, ?,?)');$s->execute([$userId,$categoryId,$planId,$amount,$amount,$currency,'pending',$now,$now]);return (int)$this->db->lastInsertId(); }
 public function applyCoupon(int $orderId,int $userId,string $code): int { $q=$this->db->prepare('SELECT * FROM orders WHERE id=? AND telegram_user_id=? AND status="pending"');$q->execute([$orderId,$userId]);$o=$q->fetch(PDO::FETCH_ASSOC);if(!$o)throw new RuntimeException('Order not found.');$q=$this->db->prepare('SELECT * FROM coupons WHERE code=? AND enabled=1');$q->execute([strtoupper(trim($code))]);$c=$q->fetch(PDO::FETCH_ASSOC);if(!$c)throw new RuntimeException('Coupon is invalid.');$base=(int)$o['subtotal_minor'];$d=$c['discount_type']==='percent'?(int)floor($base*min(100,(int)$c['discount_value'])/100):min($base,(int)$c['discount_value']);$this->db->prepare('UPDATE orders SET discount_minor=?,total_minor=?,coupon_code=?,updated_at=? WHERE id=?')->execute([$d,max(0,$base-$d),$c['code'],time(),$orderId]);return max(0,$base-$d); }
 public function markPaid(int $orderId,string $provider,string $authority,string $transactionId): void { $this->db->prepare('UPDATE orders SET status="paid",payment_provider=?,payment_authority=?,transaction_id=?,paid_at=?,updated_at=? WHERE id=? AND status="pending"')->execute([$provider,$authority,$transactionId,time(),time(),$orderId]); }
}
