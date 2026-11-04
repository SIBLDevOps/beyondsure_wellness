<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\CycleController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DispatchController;
use App\Http\Controllers\Admin\MemberController as AdminMemberController;
use App\Http\Controllers\Admin\MessageController as AdminMessageController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\GuideController;
use App\Http\Controllers\Admin\RegionController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\SiteContentController;
use App\Http\Controllers\Admin\TestimonialController as AdminTestimonialController;
use App\Http\Controllers\Admin\WithdrawalController as AdminWithdrawalController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BuyController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Member\DownlineController;
use App\Http\Controllers\Member\OrderController as MemberOrderController;
use App\Http\Controllers\Member\PlanController as MemberPlanController;
use App\Http\Controllers\Member\ReportController as MemberReportController;
use App\Http\Controllers\Member\WalletController;
use App\Http\Controllers\MemberDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store')->middleware('throttle:contact');
Route::get('/buy/{plan}', [BuyController::class, 'start'])->name('buy.start');

// Links inside emails. Both are signed, so they only work for the exact link that was sent (and the invoice one expires).
Route::get('/invoice/{order}/shared', [\App\Http\Controllers\InvoiceController::class, 'shared'])->whereNumber('order')->middleware('signed')->name('invoice.shared');
Route::get('/email/unsubscribe/{member}', [\App\Http\Controllers\EmailPreferenceController::class, 'unsubscribe'])->middleware('signed')->name('email.unsubscribe');

Route::middleware('guest:member')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.send');
    Route::get('/register/verify', [RegisterController::class, 'verifyForm'])->name('register.verify');
    Route::post('/register/verify', [RegisterController::class, 'verify'])->name('register.verify.submit');
    Route::post('/register/resend', [RegisterController::class, 'resend'])->name('register.resend');

    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.send');
    Route::get('/login/verify', [LoginController::class, 'verifyForm'])->name('login.verify');
    Route::post('/login/verify', [LoginController::class, 'verify'])->name('login.verify.submit');
    Route::post('/login/resend', [LoginController::class, 'resend'])->name('login.resend');
});

Route::middleware('auth:member')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    // Buying comes first: the plans and the member's own orders (to pay them) are open to everyone signed in.
    Route::get('/plans', [MemberPlanController::class, 'index'])->name('member.plans.index');
    Route::post('/plans/{plan}/buy', [MemberPlanController::class, 'buy'])->name('member.plans.buy');

    Route::get('/orders', [MemberOrderController::class, 'index'])->name('member.orders.index');
    Route::get('/orders/{order}/pay', [MemberOrderController::class, 'pay'])->name('member.orders.pay');
    Route::get('/orders/{order}/invoice', [\App\Http\Controllers\InvoiceController::class, 'member'])->name('member.orders.invoice');
    Route::get('/orders/{order}/credit-note', [\App\Http\Controllers\CreditNoteController::class, 'member'])->name('member.orders.credit-note');
    Route::post('/orders/{order}/invoice/email', [\App\Http\Controllers\InvoiceController::class, 'email'])->middleware('throttle:invoice-email')->name('member.orders.invoice.email');
    Route::post('/orders/{order}/pay', [MemberOrderController::class, 'submitPayment'])->name('member.orders.submit-payment');
    Route::post('/orders/{order}/razorpay-order', [MemberOrderController::class, 'razorpayOrder'])->name('member.orders.razorpay-order');
    Route::post('/orders/{order}/razorpay-callback', [MemberOrderController::class, 'razorpayCallback'])->name('member.orders.razorpay-callback');

    // Everything below opens only after the member's first payment is approved.
    Route::middleware('member.bought')->group(function () {
        Route::get('/dashboard', [MemberDashboardController::class, 'index'])->name('member.dashboard');
        Route::post('/invite', [\App\Http\Controllers\Member\InviteController::class, 'send'])->middleware('throttle:member-invite')->name('member.invite.send');

        Route::get('/wallet', [WalletController::class, 'index'])->name('member.wallet.index');
        Route::post('/wallet/withdraw', [WalletController::class, 'requestWithdrawal'])->name('member.wallet.request');
        Route::put('/wallet/bank-details', [WalletController::class, 'updateBankDetails'])->name('member.wallet.bank-details');

        Route::get('/downline', [DownlineController::class, 'index'])->name('member.downline.index');
        Route::get('/team', [DownlineController::class, 'team'])->name('member.team.index');
        Route::get('/pending', [DownlineController::class, 'pending'])->name('member.pending.index');
        Route::get('/levels', [DownlineController::class, 'levels'])->name('member.levels.index');
        Route::get('/reports', [MemberReportController::class, 'index'])->name('member.reports.index');
    });
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:web')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'create'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'store'])->name('login.submit')->middleware('throttle:admin-login');
        // The second step of signing in, for staff who switched on two-factor sign-in.
        Route::get('/two-factor-challenge', [\App\Http\Controllers\Admin\TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
        Route::post('/two-factor-challenge', [\App\Http\Controllers\Admin\TwoFactorChallengeController::class, 'store'])->middleware('throttle:two-factor')->name('two-factor.verify');
    });

    Route::middleware(['auth:web', 'admin', \App\Http\Middleware\AuditAdminActions::class])->group(function () {
        Route::get('cycles/simulate', [\App\Http\Controllers\Admin\CycleSimulationController::class, 'show'])->name('cycles.simulate');
        Route::post('cycles/simulate', [\App\Http\Controllers\Admin\CycleSimulationController::class, 'run'])->name('cycles.simulate.run');
        Route::get('refunds', [\App\Http\Controllers\Admin\RefundController::class, 'index'])->name('refunds.index');
        Route::get('audit-log',[\App\Http\Controllers\Admin\AuditLogController::class, 'index'])->name('audit-log.index');
        Route::get('audit-log/download', [\App\Http\Controllers\Admin\AuditLogController::class, 'csv'])->name('audit-log.csv');
        Route::get('account/two-factor', [\App\Http\Controllers\Admin\TwoFactorController::class, 'show'])->name('account.two-factor');
        Route::post('account/two-factor', [\App\Http\Controllers\Admin\TwoFactorController::class, 'enable'])->name('account.two-factor.enable');
        Route::post('account/two-factor/confirm', [\App\Http\Controllers\Admin\TwoFactorController::class, 'confirm'])->name('account.two-factor.confirm');
        Route::delete('account/two-factor', [\App\Http\Controllers\Admin\TwoFactorController::class, 'disable'])->name('account.two-factor.disable');
        Route::put('team/{user}/two-factor-reset', [TeamController::class, 'resetTwoFactor'])->name('team.two-factor-reset');
        Route::get('orders/{order}/credit-note', [\App\Http\Controllers\CreditNoteController::class, 'admin'])->name('orders.credit-note');
        Route::post('orders/{order}/credit-note', [\App\Http\Controllers\CreditNoteController::class, 'store'])->name('orders.credit-note.store');
        Route::get('reports/gst', [ReportController::class, 'gst'])->name('reports.gst');
        Route::get('reports/gst/csv', [ReportController::class, 'gstCsv'])->name('reports.gst.csv');

        Route::post('/logout', [AdminAuthController::class, 'destroy'])->name('logout');
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::resource('categories', AdminCategoryController::class)->except('show', 'destroy')
            ->parameters(['categories' => 'category']);
        Route::delete('categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');

        Route::resource('products', AdminProductController::class)->except('show')
            ->parameters(['products' => 'product']);

        Route::put('plans/{plan}/toggle', [AdminPlanController::class, 'toggle'])->name('plans.toggle');
        Route::resource('plans', AdminPlanController::class)->except('show', 'destroy')
            ->parameters(['plans' => 'plan']);

        Route::get('messages', [AdminMessageController::class, 'index'])->name('messages.index');
        Route::put('messages/{message}/read', [AdminMessageController::class, 'markRead'])->name('messages.read');

        Route::get('activation', [\App\Http\Controllers\Admin\ActivationController::class, 'index'])->name('activation.index');
        Route::get('members', [AdminMemberController::class, 'index'])->name('members.index');
        Route::get('members/{member}', [AdminMemberController::class, 'show'])->name('members.show');
        Route::get('members/{member}/network', [AdminMemberController::class, 'network'])->name('members.network');
        Route::get('members/{member}/levels', [AdminMemberController::class, 'levels'])->name('members.levels');

        Route::get('announcements', [\App\Http\Controllers\Admin\AnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('announcements', [\App\Http\Controllers\Admin\AnnouncementController::class, 'store'])->name('announcements.store');
        Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}/invoice', [\App\Http\Controllers\InvoiceController::class, 'admin'])->name('orders.invoice');
        Route::delete('orders/{order}', [AdminOrderController::class, 'cancel'])->name('orders.cancel');

        Route::get('payments', [AdminPaymentController::class, 'index'])->name('payments.index');
        Route::put('payments/{payment}/verify', [AdminPaymentController::class, 'verify'])->name('payments.verify');
        Route::put('payments/{payment}/reject', [AdminPaymentController::class, 'reject'])->name('payments.reject');

        Route::get('cycles', [CycleController::class, 'index'])->name('cycles.index');
        Route::post('cycles', [CycleController::class, 'store'])->name('cycles.store');
        Route::put('cycles/{cycle}/approve', [CycleController::class, 'approve'])->name('cycles.approve');

        Route::get('withdrawals', [AdminWithdrawalController::class, 'index'])->name('withdrawals.index');
        Route::put('withdrawals/{withdrawal}/approve', [AdminWithdrawalController::class, 'approve'])->name('withdrawals.approve');
        Route::put('withdrawals/{withdrawal}/reject', [AdminWithdrawalController::class, 'reject'])->name('withdrawals.reject');
        Route::put('withdrawals/{withdrawal}/mark-paid', [AdminWithdrawalController::class, 'markPaid'])->name('withdrawals.mark-paid');

        Route::get('dispatch', [DispatchController::class, 'index'])->name('dispatch.index');
        Route::post('dispatch', [DispatchController::class, 'store'])->name('dispatch.store');
        Route::get('dispatch/{batch}', [DispatchController::class, 'show'])->name('dispatch.show');
        Route::post('dispatch/{batch}/items', [DispatchController::class, 'addItem'])->name('dispatch.add-item');
        Route::delete('dispatch/{batch}/items/{withdrawal}', [DispatchController::class, 'removeItem'])->name('dispatch.remove-item');
        Route::put('dispatch/{batch}/release', [DispatchController::class, 'release'])->name('dispatch.release');

        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::put('settings/ranks', [SettingsController::class, 'updateRanks'])->name('settings.ranks');

        Route::get('account/password', [\App\Http\Controllers\Admin\AccountController::class, 'edit'])->name('account.password');
        Route::put('account/password', [\App\Http\Controllers\Admin\AccountController::class, 'update'])->name('account.password.update');

        Route::get('guide', [GuideController::class, 'index'])->name('guide.index');

        Route::post('regions/{region}/root', [RegionController::class, 'root'])->name('regions.root');
        Route::resource('regions', RegionController::class)->except('show', 'destroy')
            ->parameters(['regions' => 'region']);

        Route::put('team/{user}/password',[TeamController::class, 'resetPassword'])->name('team.password');
        Route::resource('team', TeamController::class)->except('show')->parameters(['team' => 'user']);

        Route::get('site-content', [SiteContentController::class, 'edit'])->name('site-content.edit');
        Route::put('site-content', [SiteContentController::class, 'update'])->name('site-content.update');

        Route::resource('testimonials', AdminTestimonialController::class)->except('show')
            ->parameters(['testimonials' => 'testimonial']);

        Route::get('reports/overview', [ReportController::class, 'overview'])->name('reports.overview');
        Route::get('reports/overview/trend/csv', [ReportController::class, 'overviewTrendCsv'])->name('reports.overview.trend.csv');

        Route::get('reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
        Route::get('reports/sales/csv', [ReportController::class, 'salesCsv'])->name('reports.sales.csv');
        Route::get('reports/sales/top-buyers/csv', [ReportController::class, 'salesTopBuyersCsv'])->name('reports.sales.top-buyers.csv');
        Route::get('reports/sales/purchases/csv', [ReportController::class, 'salesPurchasesCsv'])->name('reports.sales.purchases.csv');

        Route::get('reports/payouts', [ReportController::class, 'payouts'])->name('reports.payouts');
        Route::get('reports/payouts/csv', [ReportController::class, 'payoutsCsv'])->name('reports.payouts.csv');
        Route::get('reports/payouts/by-type/csv', [ReportController::class, 'payoutsByTypeCsv'])->name('reports.payouts.by-type.csv');
        Route::get('reports/payouts/top-earners/csv', [ReportController::class, 'payoutsTopEarnersCsv'])->name('reports.payouts.top-earners.csv');

        Route::get('reports/levels', [ReportController::class, 'levels'])->name('reports.levels');
        Route::get('reports/levels/csv', [ReportController::class, 'levelsCsv'])->name('reports.levels.csv');
        Route::get('reports/levels/earners/csv', [ReportController::class, 'levelsEarnersCsv'])->name('reports.levels.earners.csv');

        Route::get('reports/cascade', [ReportController::class, 'cascade'])->name('reports.cascade');
        Route::get('reports/cascade/csv', [ReportController::class, 'cascadeCsv'])->name('reports.cascade.csv');
        Route::get('reports/cascade/pdf', [ReportController::class, 'cascadePdf'])->name('reports.cascade.pdf');

        Route::get('reports/pending', [ReportController::class, 'pending'])->name('reports.pending');
        Route::get('reports/pending/csv', [ReportController::class, 'pendingCsv'])->name('reports.pending.csv');
        Route::get('reports/pending/withdrawals/csv', [ReportController::class, 'pendingWithdrawalsCsv'])->name('reports.pending.withdrawals.csv');
        Route::get('reports/pending/unsettled/csv', [ReportController::class, 'pendingUnsettledCsv'])->name('reports.pending.unsettled.csv');

        Route::get('reports/compliance', [ReportController::class, 'compliance'])->name('reports.compliance');
        Route::get('reports/compliance/csv', [ReportController::class, 'complianceCsv'])->name('reports.compliance.csv');
        Route::get('reports/compliance/overpaid/csv', [ReportController::class, 'complianceOverpaidCsv'])->name('reports.compliance.overpaid.csv');
        Route::get('reports/compliance/cost-cap/csv', [ReportController::class, 'complianceCostCapCsv'])->name('reports.compliance.cost-cap.csv');

        Route::get('reports/repurchase', [ReportController::class, 'repurchase'])->name('reports.repurchase');
        Route::get('reports/repurchase/csv', [ReportController::class, 'repurchaseCsv'])->name('reports.repurchase.csv');
        Route::get('reports/repurchase/orders/csv', [ReportController::class, 'repurchaseOrdersCsv'])->name('reports.repurchase.orders.csv');
        Route::get('reports/repurchase/buyers/csv', [ReportController::class, 'repurchaseBuyersCsv'])->name('reports.repurchase.buyers.csv');
    });
});
