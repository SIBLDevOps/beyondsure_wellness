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
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');
Route::get('/buy/{plan}', [BuyController::class, 'start'])->name('buy.start');

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
    Route::get('/dashboard', [MemberDashboardController::class, 'index'])->name('member.dashboard');

    Route::get('/plans', [MemberPlanController::class, 'index'])->name('member.plans.index');
    Route::post('/plans/{plan}/buy', [MemberPlanController::class, 'buy'])->name('member.plans.buy');

    Route::get('/orders', [MemberOrderController::class, 'index'])->name('member.orders.index');
    Route::get('/orders/{order}/pay', [MemberOrderController::class, 'pay'])->name('member.orders.pay');
    Route::post('/orders/{order}/pay', [MemberOrderController::class, 'submitPayment'])->name('member.orders.submit-payment');
    Route::post('/orders/{order}/razorpay-order', [MemberOrderController::class, 'razorpayOrder'])->name('member.orders.razorpay-order');
    Route::post('/orders/{order}/razorpay-callback', [MemberOrderController::class, 'razorpayCallback'])->name('member.orders.razorpay-callback');

    Route::get('/wallet', [WalletController::class, 'index'])->name('member.wallet.index');
    Route::post('/wallet/withdraw', [WalletController::class, 'requestWithdrawal'])->name('member.wallet.request');
    Route::put('/wallet/bank-details', [WalletController::class, 'updateBankDetails'])->name('member.wallet.bank-details');

    Route::get('/downline', [DownlineController::class, 'index'])->name('member.downline.index');
    Route::get('/team', [DownlineController::class, 'team'])->name('member.team.index');
    Route::get('/levels', [DownlineController::class, 'levels'])->name('member.levels.index');
    Route::get('/reports', [MemberReportController::class, 'index'])->name('member.reports.index');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:web')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'create'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'store'])->name('login.submit');
    });

    Route::middleware(['auth:web', 'admin'])->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'destroy'])->name('logout');
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::resource('categories', AdminCategoryController::class)->except('show', 'destroy')
            ->parameters(['categories' => 'category']);
        Route::delete('categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');

        Route::resource('products', AdminProductController::class)->except('show')
            ->parameters(['products' => 'product']);

        Route::resource('plans', AdminPlanController::class)->except('show', 'destroy')
            ->parameters(['plans' => 'plan']);

        Route::get('messages', [AdminMessageController::class, 'index'])->name('messages.index');
        Route::put('messages/{message}/read', [AdminMessageController::class, 'markRead'])->name('messages.read');

        Route::get('members', [AdminMemberController::class, 'index'])->name('members.index');
        Route::get('members/{member}', [AdminMemberController::class, 'show'])->name('members.show');
        Route::get('members/{member}/network', [AdminMemberController::class, 'network'])->name('members.network');
        Route::get('members/{member}/levels', [AdminMemberController::class, 'levels'])->name('members.levels');

        Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
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
