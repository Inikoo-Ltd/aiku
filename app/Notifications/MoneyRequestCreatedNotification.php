<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-06 14:09
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Notifications;

use App\Models\SupplyChain\DepositRequest;
use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class MoneyRequestCreatedNotification extends Notification
{
    use SendsMailThroughSes;

    public const string FOR_PAYER = 'payer';
    public const string FOR_COPY = 'copy';
    public const string FOR_REQUESTER = 'requester';

    public bool $byEmail = true;

    /**
     * @param  list<array{description: string, amount: string, purchase_order: array{reference: string, url: string, submitted_at: string|null, placed_by: string|null, supplier_code: string|null, supplier_company: string|null, supplier_nickname: string|null, supplier_url: string|null}|null}>  $lines
     * @param  array{name: string, parameters: array<int|string, mixed>}  $route
     */
    public function __construct(
        public DepositRequest $depositRequest,
        public string $requesterName,
        public string $amount,
        public array $lines,
        public string $recipientRole,
        public array $route,
        public bool $isReminder = false
    ) {
    }

    public function via($notifiable): array
    {
        return $this->byEmail ? ['database', 'mail'] : ['database'];
    }

    public function title(): string
    {
        $parameters = [
            'agent'     => $this->depositRequest->agent->code,
            'client'    => $this->depositRequest->organisation->code,
            'amount'    => $this->amount,
            'reference' => $this->reference(),
        ];

        return $this->isReminder
            ? __('Reminder: :agent still asks :client for :amount (money request :reference)', $parameters)
            : __(':agent asks :client for :amount (money request :reference)', $parameters);
    }

    public function body(): string
    {
        return $this->recipientRole === self::FOR_PAYER
            ? __(':user, from the agent, asked for it. Approve the request, then pay it with the bank statement of the transfer.', ['user' => $this->requesterName])
            : __(':user, from the agent, asked for it.', ['user' => $this->requesterName]);
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title(),
            'body'  => $this->body(),
            'type'  => $this->isReminder ? 'money_request_reminder' : 'money_request_created',
            'slug'  => (string) $this->depositRequest->id,
            'route' => $this->route,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $message = $this->newMailMessage()
            ->subject($this->title())
            ->line($this->title())
            ->line($this->body());

        $client = $this->depositRequest->organisation;
        $message->line(new HtmlString(e(__('Organisation')).': <strong>'.e($client->name).'</strong> ('.e($client->code).')'));

        if ($this->lines) {
            $message->line($this->linesTable());
        }

        if ($this->depositRequest->notes) {
            $message->line(__('Note from the agent: :notes', ['notes' => $this->depositRequest->notes]));
        }

        return $message
            ->action($this->recipientRole === self::FOR_PAYER ? __('Approve and pay') : __('Open the money request'), route($this->route['name'], $this->route['parameters']))
            ->line(match ($this->recipientRole) {
                self::FOR_PAYER     => __('You get this email because you approve and pay the money requests of this agent.'),
                self::FOR_REQUESTER => __('You get this email because you asked for this money.'),
                default             => __('You get this email because you are copied on the money requests of this agent.'),
            });
    }

    private function linesTable(): HtmlString
    {
        $cell = 'padding: 10px 8px 10px 0; border-top: 1px solid #e5e7eb; vertical-align: top; font-size: 14px; line-height: 1.5;';
        $grey = 'color: #6b7280;';
        $link = fn (string $text, ?string $url, string $style = '') => $url
            ? '<a href="'.e($url).'" style="text-decoration: none; '.$style.'">'.e($text).'</a>'
            : '<span style="'.$style.'">'.e($text).'</span>';

        $rows = array_map(function (array $line) use ($cell, $grey, $link) {
            $purchaseOrder = $line['purchase_order'];

            if (!$purchaseOrder) {
                $details = '<strong>'.e($line['description']).'</strong>';
            } else {
                $supplier = $link($purchaseOrder['supplier_company'] ?? $purchaseOrder['supplier_code'] ?? '', $purchaseOrder['supplier_url'], 'font-weight: 600;')
                    .($purchaseOrder['supplier_code'] ? ' <span style="'.$grey.'">('.e($purchaseOrder['supplier_code']).')</span>' : '');

                $facts = array_filter([
                    e($line['description']).' '.$link($purchaseOrder['reference'], $purchaseOrder['url'], 'white-space: nowrap;'),
                    $purchaseOrder['placed_by'] ? e(__('Placed by :user', ['user' => $purchaseOrder['placed_by']])) : null,
                    $purchaseOrder['submitted_at'] ? e(__('Submitted :date', ['date' => $purchaseOrder['submitted_at']])) : null,
                ]);

                $details = $supplier
                    .($purchaseOrder['supplier_nickname'] ? '<br><span style="'.$grey.'">'.e($purchaseOrder['supplier_nickname']).'</span>' : '')
                    .'<br><span style="font-size: 12px; '.$grey.'">'.implode(' · ', $facts).'</span>';
            }

            return '<tr><td style="'.$cell.'">'.$details.'</td><td style="'.$cell.' padding-right: 0; text-align: right; white-space: nowrap; font-weight: 600;">'.e($line['amount']).'</td></tr>';
        }, $this->lines);

        return new HtmlString('<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; border-collapse: collapse; margin-bottom: 8px;">'.implode('', $rows).'</table>');
    }

    private function reference(): string
    {
        return $this->depositRequest->reference ?? '#'.$this->depositRequest->id;
    }
}
