<?php

namespace App\Support;

use App\Models\ChatbotRule;

/**
 * Chatbot entries as a CSV file, for export (backup, editing in Excel) and
 * import. Columns: question, keywords, answer, status. Keywords are one
 * cell, separated by commas ("price, cost, rate"); status is "on" or
 * "off" (blank = on). Answers may span several lines (a quoted cell).
 * Attached files are not part of the CSV.
 */
class ChatbotCsv
{
    public const COLUMNS = ['question', 'keywords', 'answer', 'status'];

    // Most errors listed when an import is rejected.
    private const MAX_ERRORS_SHOWN = 5;

    /**
     * @param  iterable<ChatbotRule>  $rules
     */
    public static function export(iterable $rules): string
    {
        $out = fopen('php://temp', 'r+');

        // Excel's UTF-8 marker, so ₹ and other non-English text open correctly.
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, self::COLUMNS, ',', '"', '');

        foreach ($rules as $rule) {
            fputcsv($out, [
                $rule->question,
                implode(', ', $rule->keywords),
                $rule->answer,
                $rule->enabled ? 'on' : 'off',
            ], ',', '"', '');
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /**
     * Reads and checks every row. Returns [rows, errors]: when errors is
     * not empty, nothing should be imported. A header row naming the
     * columns is required (any order; "status" is optional).
     *
     * @return array{0: list<array{row: int, question: string, keywords: list<string>, answer: string, enabled: bool|null}>, 1: list<string>}
     */
    public static function parse(string $text): array
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text); // Excel's UTF-8 marker
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252'); // older Excel CSVs
        }

        // Excel doesn't always use ",": "Text (Tab delimited)" and some
        // older versions save with tabs, and some regions use ";".
        $firstLine = strtok($text, "\r\n") ?: '';
        $delimiter = match (true) {
            str_contains($firstLine, "\t") => "\t",
            str_contains($firstLine, ';') && ! str_contains($firstLine, ',') => ';',
            default => ',',
        };

        $in = fopen('php://temp', 'r+');
        fwrite($in, $text);
        rewind($in);

        $header = fgetcsv($in, null, $delimiter, '"', '');
        $columns = array_map(fn ($name) => mb_strtolower(trim((string) $name)), $header ?: []);

        $missing = array_diff(['question', 'keywords', 'answer'], $columns);
        if ($missing) {
            fclose($in);

            return [[], ['The first row must name the columns: question, keywords, answer (and optionally status). Missing: '.implode(', ', $missing).'.']];
        }

        $rows = [];
        $errors = [];
        $seen = [];
        $line = 1;

        while (($fields = fgetcsv($in, null, $delimiter, '"', '')) !== false) {
            $line++;

            $get = fn (string $column) => ($index = array_search($column, $columns, true)) === false
                ? ''
                : trim((string) ($fields[$index] ?? ''));

            $question = $get('question');
            $answer = $get('answer');
            $keywordsText = $get('keywords');
            $status = mb_strtolower($get('status'));

            // A blank line (or a row of empty cells) is skipped.
            if ($question === '' && $answer === '' && $keywordsText === '') {
                continue;
            }

            $keywords = ChatbotRule::parseKeywords($keywordsText);

            $error = match (true) {
                $question === '' => 'the question is empty.',
                mb_strlen($question) > 150 => 'the question is longer than 150 characters.',
                $answer === '' => 'the answer is empty.',
                mb_strlen($answer) > 4096 => 'the answer is longer than 4096 characters.',
                ($keywordsError = ChatbotRule::keywordsError($keywords)) !== null => lcfirst($keywordsError),
                ! in_array($status, ['', 'on', 'off', 'yes', 'no', '1', '0'], true) => 'status must be "on" or "off".',
                isset($seen[mb_strtolower($question)]) => 'the same question is already on row '.$seen[mb_strtolower($question)].'.',
                default => null,
            };

            if ($error !== null) {
                $errors[] = "Row {$line}: {$error}";

                continue;
            }

            $seen[mb_strtolower($question)] = $line;
            $rows[] = [
                'row' => $line,
                'question' => $question,
                'keywords' => $keywords,
                'answer' => $answer,
                // null = no status given (keep an existing entry's setting).
                'enabled' => $status === '' ? null : in_array($status, ['on', 'yes', '1'], true),
            ];
        }

        fclose($in);

        if (count($errors) > self::MAX_ERRORS_SHOWN) {
            $more = count($errors) - self::MAX_ERRORS_SHOWN;
            $errors = [...array_slice($errors, 0, self::MAX_ERRORS_SHOWN), "…and {$more} more ".($more === 1 ? 'row' : 'rows').' with problems.'];
        }

        return [$rows, $errors];
    }
}
