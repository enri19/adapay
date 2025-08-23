<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OrdersExport implements FromQuery, WithHeadings, WithMapping
{
  private $q;
  private $tz;

  public function __construct($query, string $tz = 'Asia/Jakarta')
  {
    $this->q  = $query;
    $this->tz = $tz;
  }

  public function query()
  {
    return $this->q;
  }

  public function headings(): array
  {
    return ['Order ID','Client','Voucher ID','Buyer','Email','Phone','Amount','Currency','Status','Paid At','Created At'];
  }

  public function map($o): array
  {
    $paidAt = $o->paid_at ? Carbon::parse($o->paid_at)->timezone($this->tz)->format('Y-m-d H:i:s') : '';
    $crtAt  = $o->created_at ? Carbon::parse($o->created_at)->timezone($this->tz)->format('Y-m-d H:i:s') : '';
    return [
      $o->order_id,
      $o->client_id ?: 'DEFAULT',
      (int)$o->hotspot_voucher_id,
      $o->buyer_name ?: '',
      $o->buyer_email ?: '',
      $o->buyer_phone ?: '',
      (int)($o->amount ?? 0),
      $o->currency ?: 'IDR',
      strtoupper($o->payment_status ?? 'PENDING'),
      $paidAt,
      $crtAt,
    ];
  }
}
