<?php

declare(strict_types=1);

class ShowLog extends IPSModule
{
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyInteger('ReadInterval', 60);
        $this->RegisterPropertyInteger('Hours', 24);
        $this->RegisterTimer('ReadLog', 0, 'SHL_ReadLog($_IPS[\'TARGET\']);');
        $this->RegisterVariableString('Errors', 'Fehler im Log', '', 1);
        IPS_SetHidden($this->GetIDForIdent('Errors'), false);
        $this->SetJSONPresentation();
        $this->SetValue('Errors', '{}');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $interval = max(5, min(3600, $this->ReadPropertyInteger('ReadInterval')));
        $this->SetTimerInterval('ReadLog', $interval * 1000);
        $this->MaintainVariable('ErrorCount', 'Anzahl der Fehlereinträge', VARIABLETYPE_INTEGER, '', 2, true);
        $this->SetJSONPresentation();
        $this->ReadLog();
    }

    public function ReadLog(): void
    {
        $this->SetJSONPresentation();
        $hours = max(1, min(720, $this->ReadPropertyInteger('Hours')));
        $cutoff = time() - ($hours * 3600);
        $file = $this->GetLatestLogFile();
        $entries = [];
        if ($file !== null && is_readable($file)) {
            $this->ReadFile($file, $cutoff, $entries);
        }

        $uniqueEntries = [];
        foreach ($entries as $entry) {
            $key = hash('sha256', serialize([
                $entry['timestampUnix'],
                $entry['senderId'],
                $entry['type'],
                $entry['sender'],
                $entry['message']
            ]));
            $uniqueEntries[$key] = $entry;
        }
        $entries = array_values($uniqueEntries);

        usort($entries, static function (array $a, array $b): int {
            return $b['timestampUnix'] <=> $a['timestampUnix'];
        });

        $output = [
            'generatedAt' => date(DATE_ATOM),
            'periodHours' => $hours,
            'sourceFile' => $file !== null ? basename($file) : null,
            'count' => count($entries),
            'errors' => array_map(static function (array $entry): array {
                return [
                    'timestamp' => date(DATE_ATOM, $entry['timestampUnix']),
                    'senderId' => $entry['senderId'],
                    'sender' => $entry['sender'],
                    'message' => $entry['message']
                ];
            }, $entries)
        ];
        $this->SetValue('ErrorCount', count($entries));
        $json = json_encode(
            $output,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        $this->SetValue('Errors', $json !== false ? $json : '{}');
    }

    private function GetLatestLogFile(): ?string
    {
        $logDir = rtrim(IPS_GetLogDir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $files = glob($logDir . 'logfile*.log');
        if (!is_array($files)) {
            return null;
        }

        $candidates = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (preg_match('/^logfile(\d*)\.log$/', $name, $matches) !== 1 || !is_file($file) || !is_readable($file)) {
                continue;
            }
            $candidates[] = [
                'path' => $file,
                'modified' => (int)@filemtime($file),
                'fileTimestamp' => ($matches[1] !== '') ? (int)$matches[1] : 0
            ];
        }

        if (count($candidates) === 0) {
            return null;
        }

        usort($candidates, static function (array $a, array $b): int {
            if ($a['modified'] !== $b['modified']) {
                return $b['modified'] <=> $a['modified'];
            }
            return $b['fileTimestamp'] <=> $a['fileTimestamp'];
        });
        return $candidates[0]['path'];
    }

    private function SetJSONPresentation(): void
    {
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('Errors'), [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'MULTILINE' => true
        ]);
    }

    private function ReadFile(string $file, int $cutoff, array &$entries): void
    {
        $handle = @fopen($file, 'rb');
        if ($handle === false) {
            return;
        }

        $current = null;
        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");
            $parsed = $this->ParseLogLine($line);
            if ($parsed !== null) {
                if ($current !== null && $current['type'] === 'ERROR' && $current['timestampUnix'] >= $cutoff) {
                    $entries[] = $current;
                }
                $current = $parsed;
            } elseif ($current !== null) {
                $current['message'] .= "\n" . $line;
            }
        }

        if ($current !== null && $current['type'] === 'ERROR' && $current['timestampUnix'] >= $cutoff) {
            $entries[] = $current;
        }
        fclose($handle);
    }

    private function ParseLogLine(string $line): ?array
    {
        // Aktuelles Symcon-Logformat: Datum | Sender-ID | Typ | Sender | Meldung
        if (preg_match('/^(\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}:\d{2})\s*\|\s*(\d+)\s*\|\s*([A-Z]+)\s*\|\s*(.*?)\s*\|\s?(.*)$/u', $line, $matches) === 1) {
            $date = DateTimeImmutable::createFromFormat('!d.m.Y H:i:s', $matches[1]);
            if ($date === false) {
                return null;
            }
            return [
                'timestampUnix' => $date->getTimestamp(),
                'senderId' => (int)$matches[2],
                'type' => $matches[3],
                'sender' => trim($matches[4]),
                'message' => $matches[5]
            ];
        }

        // Rückwärtskompatibilität für ISO-formatierte Logs.
        if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\s*\|\s*([A-Z]+)\s*\|\s*(.*?)\s*\|\s?(.*)$/u', $line, $matches) === 1) {
            $timestamp = strtotime($matches[1]);
            if ($timestamp === false) {
                return null;
            }
            $senderID = null;
            $sender = trim($matches[3]);
            if (preg_match('/^(\d+)\s+(.*)$/u', $sender, $senderMatches) === 1) {
                $senderID = (int)$senderMatches[1];
                $sender = trim($senderMatches[2]);
            }
            return [
                'timestampUnix' => $timestamp,
                'senderId' => $senderID,
                'type' => $matches[2],
                'sender' => $sender,
                'message' => $matches[4]
            ];
        }
        return null;
    }
}
