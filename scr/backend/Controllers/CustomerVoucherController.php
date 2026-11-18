<?php

declare(strict_types=1);

final class CustomerVoucherController extends Controller
{
    public function wallet(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login?return_to=' . rawurlencode('/voucher-cua-toi'));
        }
        if ($user['role'] !== 'customer') {
            throw new HttpException(403, 'Chỉ khách hàng mới có ví voucher.');
        }
        $userId = (int) $user['id'];
        $wallet = (new VoucherWalletService())->walletForUser($userId);
        $tab = $_GET['tab'] ?? 'available';
        if (!in_array($tab, ['available', 'used', 'expired'], true)) {
            $tab = 'available';
        }
        $vouchers = array_values(array_filter(
            $wallet,
            static fn (array $voucher): bool => $voucher['wallet_status'] === $tab
        ));
        $this->view('client/voucher-wallet', [
            'title' => 'Voucher của tôi',
            'activeTab' => $tab,
            'vouchers' => $vouchers,
            'tabCounts' => [
                'available' => count(array_filter($wallet, static fn (array $voucher): bool => $voucher['wallet_status'] === 'available')),
                'used' => count(array_filter($wallet, static fn (array $voucher): bool => $voucher['wallet_status'] === 'used')),
                'expired' => count(array_filter($wallet, static fn (array $voucher): bool => $voucher['wallet_status'] === 'expired')),
            ],
            'success' => flash('success'),
            'error' => flash('error'),
            'loadVoucherWallet' => true,
        ]);
    }

    public function shop(): void
    {
        $user = Auth::user();
        $this->view('client/voucher-shop', [
            'title' => 'Kho voucher',
            'vouchers' => (new VoucherWalletService())->publicVouchers($user ? (int) $user['id'] : null),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function claim(string $id): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login?return_to=' . rawurlencode('/kho-voucher'));
        }
        if ($user['role'] !== 'customer') {
            throw new HttpException(403, 'Chỉ khách hàng mới có thể lưu voucher.');
        }
        $voucherId = filter_var($id, FILTER_VALIDATE_INT);
        if ($voucherId === false || $voucherId < 1) {
            flash('error', 'Voucher không hợp lệ.');
            redirect('/kho-voucher');
        }

        try {
            $claimed = (new VoucherWalletService())->claimPublicVoucher((int) $user['id'], (int) $voucherId);
            flash('success', $claimed ? 'Đã lưu voucher vào ví của bạn.' : 'Voucher này đã có trong ví của bạn.');
        } catch (InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        } catch (Throwable $exception) {
            error_log('Customer voucher claim failed: ' . $exception->getMessage());
            flash('error', 'Không thể lưu voucher lúc này. Vui lòng thử lại sau.');
        }
        redirect('/kho-voucher');
    }
}
