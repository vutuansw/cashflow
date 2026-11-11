<?php

namespace App\Http\Controllers\GroupAdmin;

use App\Http\Controllers\Controller;
use App\Http\Presenters\TransactionPresenter;
use App\Models\Contact;
use App\Models\Group;
use App\Models\SepayTransaction;
use App\Models\Transaction;
use App\Services\Transactions\GroupStats;

/** Trang Tổng quan của quản trị nhóm, xem plan/03 mục 6. */
class OverviewController extends Controller
{
    public function __invoke(Group $group, GroupStats $stats)
    {
        $summary = $stats->summary($group);
        $previous = $stats->summary($group, $summary['year'] - 1);
        $month = (int) now()->month;

        $sameTime = fn (array $s) => [
            'income' => array_sum(array_column(array_slice($s['months'], 0, $month), 'income')),
            'expense' => array_sum(array_column(array_slice($s['months'], 0, $month), 'expense')),
        ];

        return [
            'summary' => $summary,
            'campaigns' => $stats->openCampaigns($group),
            'compare' => ['this_year' => $sameTime($summary), 'last_year' => $sameTime($previous), 'until_month' => $month],
            'pending_sepay' => SepayTransaction::query()->where('status', 'pending')->count(),
            'pending_posts' => StatusPostReviewController::pendingCount(),
            'counts' => [
                'contacts' => Contact::query()->where('status', 'active')->count(),
                'transactions' => Transaction::query()->count(),
            ],
            'setup' => [
                'has_contacts' => Contact::query()->count() > 1,
                'has_bank' => $group->bank_code !== null && $group->bank_account_no !== null,
                'has_transactions' => Transaction::query()->exists(),
            ],
            'latest' => Transaction::query()
                ->with(['category', 'contact:id,group_id,full_name', 'campaign:id,name', 'creator:id,name'])
                ->latest('id')->limit(5)->get()
                ->map(fn (Transaction $t) => TransactionPresenter::row($t) + ['created_by' => $t->creator?->name]),
        ];
    }
}
