<?php

declare(strict_types=1);

final class AdminFaqController extends Controller
{
    public function index(): void
    {
        $this->view('admin/faq-items', [
            'title' => 'Câu hỏi thường gặp',
            'items' => (new FaqService())->allItems(),
            'categories' => FaqService::categories(),
            'success' => flash('success'),
            'error' => flash('error'),
            'menu' => (new AdminController())->menu(),
        ], 'admin');
    }

    public function edit(string $id): void
    {
        try {
            $item = (new FaqService())->item((int) $id);
        } catch (HttpException $exception) {
            if ($exception->status() !== 404) {
                throw $exception;
            }
            $this->renderNotFound();
            return;
        }
        $this->view('admin/faq-edit', [
            'title' => 'Sửa câu hỏi thường gặp',
            'item' => $item,
            'categories' => FaqService::categories(),
            'success' => flash('success'),
            'error' => flash('error'),
            'menu' => (new AdminController())->menu(),
        ], 'admin');
    }

    public function create(): void
    {
        try {
            (new FaqService())->create($_POST, (int) Auth::user()['id']);
            flash('success', 'Đã thêm câu hỏi thường gặp.');
        } catch (InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        } catch (PDOException $exception) {
            error_log('FAQ item creation failed: ' . $exception->getMessage());
            flash('error', 'Không thể thêm câu hỏi lúc này. Vui lòng thử lại sau.');
        }
        redirect('/admin/content/faqs');
    }

    public function update(string $id): void
    {
        $faqId = (int) $id;
        try {
            (new FaqService())->update($faqId, $_POST, (int) Auth::user()['id']);
            flash('success', 'Đã cập nhật câu hỏi thường gặp.');
        } catch (InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        } catch (HttpException $exception) {
            if ($exception->status() !== 404) {
                throw $exception;
            }
            $this->renderNotFound();
            return;
        } catch (PDOException $exception) {
            error_log('FAQ item update failed: ' . $exception->getMessage());
            flash('error', 'Không thể cập nhật câu hỏi lúc này. Vui lòng thử lại sau.');
        }
        redirect('/admin/content/faqs/' . $faqId . '/edit');
    }

    public function toggle(string $id): void
    {
        $faqId = (int) $id;
        try {
            $active = (new FaqService())->toggle($faqId, (int) Auth::user()['id']);
            flash('success', $active ? 'Đã hiển thị câu hỏi.' : 'Đã ẩn câu hỏi.');
        } catch (HttpException $exception) {
            if ($exception->status() !== 404) {
                throw $exception;
            }
            $this->renderNotFound();
            return;
        } catch (PDOException $exception) {
            error_log('FAQ item status update failed: ' . $exception->getMessage());
            flash('error', 'Không thể đổi trạng thái câu hỏi lúc này.');
        }
        redirect('/admin/content/faqs');
    }

    public function delete(string $id): void
    {
        $faqId = (int) $id;
        try {
            (new FaqService())->delete($faqId, (int) Auth::user()['id']);
            flash('success', 'Đã xóa câu hỏi thường gặp.');
        } catch (HttpException $exception) {
            if ($exception->status() !== 404) {
                throw $exception;
            }
            $this->renderNotFound();
            return;
        } catch (PDOException $exception) {
            error_log('FAQ item deletion failed: ' . $exception->getMessage());
            flash('error', 'Không thể xóa câu hỏi lúc này.');
        }
        redirect('/admin/content/faqs');
    }

    private function renderNotFound(): void
    {
        http_response_code(404);
        View::renderError(404);
    }
}
