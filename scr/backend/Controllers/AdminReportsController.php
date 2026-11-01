<?php

declare(strict_types=1);

final class AdminReportsController extends Controller
{
    public function index(): void
    {
        $type = (string) ($_GET['type'] ?? 'revenue');
        if (!isset(ReportService::definitions()[$type])) {
            $type = 'revenue';
            $error = 'Loại báo cáo không hợp lệ.';
        } else {
            $error = null;
        }
        $today = date('Y-m-d');
        $from = (string) ($_GET['from'] ?? date('Y-m-d', strtotime('-29 days')));
        $to = (string) ($_GET['to'] ?? $today);
        if (!$this->validDate($from) || !$this->validDate($to) || $from > $to) {
            $error = 'Khoảng ngày không hợp lệ. Vui lòng chọn ngày bắt đầu trước hoặc bằng ngày kết thúc.';
            $from = date('Y-m-d', strtotime('-29 days'));
            $to = $today;
        }
        try {
            $report = (new ReportService())->report(
                $type,
                $from,
                $to,
                max(1, (int) ($_GET['page'] ?? 1))
            );
        } catch (PDOException) {
            error_log('Admin report query failed.');
            http_response_code(503);
            $error = 'Không thể tải báo cáo lúc này. Vui lòng thử lại sau.';
            $report = [
                'definition' => ReportService::definitions()[$type],
                'rows' => [],
                'page' => 1,
                'pages' => 1,
                'total' => 0,
            ];
        }
        $this->view('admin/reports', [
            'title' => 'Báo cáo thống kê',
            'type' => $type,
            'from' => $from,
            'to' => $to,
            'report' => $report,
            'definitions' => ReportService::definitions(),
            'error' => $error,
            'menu' => (new AdminController())->menu(),
        ], 'admin');
    }

    public function exportCsv(): void
    {
        $type = (string) ($_GET['type'] ?? 'revenue');
        $from = (string) ($_GET['from'] ?? date('Y-m-d', strtotime('-29 days')));
        $to = (string) ($_GET['to'] ?? date('Y-m-d'));
        if (!isset(ReportService::definitions()[$type]) || !$this->validDate($from) || !$this->validDate($to) || $from > $to) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Thông tin xuất báo cáo không hợp lệ.';
            return;
        }

        $definition = ReportService::definitions()[$type];
        try {
            $rows = (new ReportService())->stream($type, $from, $to);
            $rows->rewind();
        } catch (PDOException) {
            error_log('Admin CSV report query failed.');
            http_response_code(503);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Không thể xuất báo cáo lúc này. Vui lòng thử lại sau.';
            return;
        }
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="bao-cao-' . $type . '.csv"');
        header('Cache-Control: no-store');
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            throw new RuntimeException('Không mở được luồng xuất báo cáo.');
        }
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, array_values($definition['columns']));
        try {
            foreach ($rows as $row) {
                $values = [];
                foreach (array_keys($definition['columns']) as $column) {
                    $values[] = ReportService::csvText($row[$column] ?? '');
                }
                fputcsv($output, $values);
            }
        } catch (PDOException) {
            error_log('Admin CSV report stream ended before all rows were exported.');
            fputcsv($output, ['Không thể xuất đầy đủ dữ liệu. Vui lòng thử lại sau.']);
        }
        fclose($output);
        exit;
    }

    private function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
