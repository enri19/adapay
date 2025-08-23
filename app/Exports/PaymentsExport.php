<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PaymentsExport implements FromQuery, WithHeadings, WithMapping
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
    return ['Order ID','Client','Provider','Channel/Ref','Amount','Currency','Status','Paid At','Created At'];
  }

  public function map($p): array
  {
    $paidAt = $p->paid_at ? Carbon::parse($p->paid_at)->timezone($this->tz)->format('Y-m-d H:i:s') : '';
    $crtAt  = $p->created_at ? Carbon::parse($p->created_at)->timezone($this->tz)->format('Y-m-d H:i:s') : '';
    $channel= $p->provider_ref ?: ($p->raw['payment_type'] ?? '');
    return [
      $p->order_id,
      $p->client_id ?: 'DEFAULT',
      $p->provider ?: 'midtrans',
      $channel,
      (int)$p->amount,
      $p->currency ?: 'IDR',
      strtoupper($p->status ?: ''),
      $paidAt,
      $crtAt,
    ];
  }
}
