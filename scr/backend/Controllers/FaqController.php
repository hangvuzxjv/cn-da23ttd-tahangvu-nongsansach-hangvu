<?php

declare(strict_types=1);

final class FaqController extends Controller
{
    public function index(): void
    {
        $groups = array_fill_keys(FaqService::categories(), []);
        foreach ((new FaqService())->activeItems() as $item) {
            if (isset($groups[$item['category']])) {
                $groups[$item['category']][] = $item;
            }
        }
        $groups = array_filter($groups, static fn (array $items): bool => $items !== []);
        $this->view('client/faqs', [
            'title' => 'Câu hỏi thường gặp',
            'metaDescription' => 'Câu trả lời về đặt hàng, thanh toán, giao hàng và hỗ trợ khách hàng.',
            'faqGroups' => $groups,
            'loadFaqSearch' => true,
        ]);
    }
}
