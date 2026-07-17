<?php

if (! class_exists('ZipArchive')) {
    final class ZipArchive implements Countable
    {
        public const CREATE = 1;
        public const EXCL = 2;
        public const OVERWRITE = 8;
        public const RDONLY = 16;

        public const FL_NOCASE = 1;

        public const CM_STORE = 0;
        public const CM_DEFLATE = 8;

        public const ER_OK = 0;
        public const ER_MULTIDISK = 1;
        public const ER_RENAME = 2;
        public const ER_CLOSE = 3;
        public const ER_SEEK = 4;
        public const ER_READ = 5;
        public const ER_WRITE = 6;
        public const ER_CRC = 7;
        public const ER_ZIPCLOSED = 8;
        public const ER_NOENT = 9;
        public const ER_EXISTS = 10;
        public const ER_OPEN = 11;
        public const ER_TMPOPEN = 12;
        public const ER_ZLIB = 13;
        public const ER_MEMORY = 14;
        public const ER_CHANGED = 15;
        public const ER_COMPNOTSUPP = 16;
        public const ER_EOF = 17;
        public const ER_INVAL = 18;
        public const ER_NOZIP = 19;
        public const ER_INTERNAL = 20;
        public const ER_INCONS = 21;
        public const ER_REMOVE = 22;
        public const ER_DELETED = 23;
        public const ER_ENCRNOTSUPP = 24;
        public const ER_RDONLY = 25;
        public const ER_NOPASSWD = 26;
        public const ER_WRONGPASSWD = 27;

        public int $status = self::ER_OK;
        public int $statusSys = self::ER_OK;
        public int $numFiles = 0;
        public string $filename = '';
        public string $comment = '';

        /** @var array<int, array{name:string,data:string,mtime:int}> */
        private array $entries = [];

        private bool $writeMode = false;

        public function count(): int
        {
            return count($this->entries);
        }

        public function open(string $filename, int $flags = 0): bool
        {
            $this->reset();
            $this->filename = $filename;

            $exists = is_file($filename);
            $this->writeMode = ($flags & self::CREATE) === self::CREATE
                || ($flags & self::OVERWRITE) === self::OVERWRITE
                || (! $exists && $flags === 0);

            if (! $exists) {
                if ($this->writeMode) {
                    $this->status = self::ER_OK;
                    return true;
                }

                $this->status = self::ER_NOENT;
                return false;
            }

            if (! $this->parseArchive($filename)) {
                $this->status = self::ER_NOZIP;
                return false;
            }

            $this->status = self::ER_OK;

            return true;
        }

        public function addEmptyDir(string $dirname): bool
        {
            $dirname = $this->normalizeEntryName($dirname);
            if ($dirname === '') {
                return false;
            }

            $this->writeMode = true;
            $this->replaceEntry($dirname.'/', '');

            return true;
        }

        public function addFile(string $filename, ?string $localname = null, int $start = 0, int $length = 0): bool
        {
            if (! is_file($filename) || ! is_readable($filename)) {
                $this->status = self::ER_NOENT;

                return false;
            }

            $contents = file_get_contents($filename);
            if ($contents === false) {
                $this->status = self::ER_READ;

                return false;
            }

            if ($start > 0 || $length > 0) {
                $contents = substr($contents, $start, $length > 0 ? $length : null);
            }

            return $this->addFromString($localname ?? basename($filename), $contents);
        }

        public function addFromString(string $localname, string $contents): bool
        {
            $name = $this->normalizeEntryName($localname);
            if ($name === '') {
                $this->status = self::ER_INVAL;

                return false;
            }

            $this->writeMode = true;
            $this->replaceEntry($name, $contents);

            return true;
        }

        public function close(): bool
        {
            if ($this->writeMode) {
                $binary = $this->buildArchiveBinary();
                $directory = dirname($this->filename);
                if ($directory !== '' && ! is_dir($directory)) {
                    @mkdir($directory, 0777, true);
                }

                if (file_put_contents($this->filename, $binary) === false) {
                    $this->status = self::ER_WRITE;

                    return false;
                }
            }

            $this->resetStateOnly();

            return true;
        }

        public function deleteIndex(int $index): bool
        {
            if (! isset($this->entries[$index])) {
                return false;
            }

            unset($this->entries[$index]);
            $this->entries = array_values($this->entries);
            $this->numFiles = count($this->entries);
            $this->writeMode = true;

            return true;
        }

        public function deleteName(string $name): bool
        {
            $index = $this->locateName($name);
            if ($index === false) {
                return false;
            }

            return $this->deleteIndex((int) $index);
        }

        public function extractTo(string $destination, mixed $entries = null): bool
        {
            if (! is_dir($destination) && ! @mkdir($destination, 0777, true) && ! is_dir($destination)) {
                return false;
            }

            $wanted = null;
            if (is_string($entries)) {
                $wanted = [$entries];
            } elseif (is_array($entries)) {
                $wanted = $entries;
            }

            foreach ($this->entries as $entry) {
                if ($wanted !== null && ! in_array($entry['name'], $wanted, true)) {
                    continue;
                }

                $path = rtrim($destination, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $entry['name']);
                $directory = dirname($path);
                if ($directory !== '' && ! is_dir($directory)) {
                    @mkdir($directory, 0777, true);
                }

                if (file_put_contents($path, $entry['data']) === false) {
                    return false;
                }
            }

            return true;
        }

        public function getArchiveComment(int $flags = 0): string
        {
            return $this->comment;
        }

        public function getCommentIndex(int $index, int $flags = 0): string
        {
            return '';
        }

        public function getCommentName(string $name, int $flags = 0): string
        {
            return '';
        }

        public function GetExternalAttributesIndex(int $index, int &$opsys, int &$attr, int $flags = 0): bool
        {
            $opsys = 0;
            $attr = 0;

            return isset($this->entries[$index]);
        }

        public function getExternalAttributesName(string $name, int &$opsys, int &$attr, int $flags = 0): bool
        {
            $opsys = 0;
            $attr = 0;

            return $this->locateName($name) !== false;
        }

        public function getFromIndex(int $index, int $length = 0, int $flags = 0): string|false
        {
            if (! isset($this->entries[$index])) {
                return false;
            }

            return $this->sliceEntryData($this->entries[$index]['data'], $length);
        }

        public function getFromName(string $name, int $length = 0, int $flags = 0): string|false
        {
            $index = $this->locateName($name, $flags);
            if ($index === false) {
                return false;
            }

            return $this->sliceEntryData($this->entries[(int) $index]['data'], $length);
        }

        public function getNameIndex(int $index, int $flags = 0): string|false
        {
            return $this->entries[$index]['name'] ?? false;
        }

        public function getStatusString(): string
        {
            return match ($this->status) {
                self::ER_OK => 'No error',
                self::ER_NOENT => 'No such file',
                self::ER_NOZIP => 'Not a valid zip archive',
                self::ER_READ => 'Read error',
                self::ER_WRITE => 'Write error',
                default => 'ZipArchive error '.$this->status,
            };
        }

        public function getStream(string $name): mixed
        {
            $data = $this->getFromName($name);
            if ($data === false) {
                return false;
            }

            $stream = fopen('php://temp', 'r+b');
            if ($stream === false) {
                return false;
            }

            fwrite($stream, $data);
            rewind($stream);

            return $stream;
        }

        public function locateName(string $name, int $flags = 0): int|false
        {
            $needle = $this->normalizeLookupName($name);

            foreach ($this->entries as $index => $entry) {
                $candidate = $this->normalizeLookupName($entry['name']);
                if (($flags & self::FL_NOCASE) === self::FL_NOCASE) {
                    if (strcasecmp($candidate, $needle) === 0) {
                        return $index;
                    }
                    continue;
                }

                if ($candidate === $needle) {
                    return $index;
                }
            }

            return false;
        }

        public function renameIndex(int $index, string $newname): bool
        {
            if (! isset($this->entries[$index])) {
                return false;
            }

            $this->entries[$index]['name'] = $this->normalizeEntryName($newname);
            $this->writeMode = true;

            return true;
        }

        public function renameName(string $name, string $newname): bool
        {
            $index = $this->locateName($name);
            if ($index === false) {
                return false;
            }

            return $this->renameIndex((int) $index, $newname);
        }

        public function setArchiveComment(string $comment): bool
        {
            $this->comment = $comment;
            $this->writeMode = true;

            return true;
        }

        public function setCommentIndex(int $index, string $comment): bool
        {
            return isset($this->entries[$index]);
        }

        public function setCommentName(string $name, string $comment): bool
        {
            return $this->locateName($name) !== false;
        }

        public function setCompressionIndex(int $index, int $comp_method, int $comp_flags = 0): bool
        {
            return isset($this->entries[$index]);
        }

        public function setCompressionName(string $name, int $comp_method, int $comp_flags = 0): bool
        {
            return $this->locateName($name) !== false;
        }

        public function setEncryptionIndex(int $index, string $method, string $password = ''): bool
        {
            return isset($this->entries[$index]);
        }

        public function setEncryptionName(string $name, string $method, string $password = ''): bool
        {
            return $this->locateName($name) !== false;
        }

        public function statIndex(int $index, int $flags = 0): array|false
        {
            if (! isset($this->entries[$index])) {
                return false;
            }

            return $this->buildStatRow($this->entries[$index], $index);
        }

        public function statName(string $name, int $flags = 0): array|false
        {
            $index = $this->locateName($name, $flags);
            if ($index === false) {
                return false;
            }

            return $this->buildStatRow($this->entries[(int) $index], (int) $index);
        }

        private function reset(): void
        {
            $this->entries = [];
            $this->comment = '';
            $this->numFiles = 0;
            $this->status = self::ER_OK;
            $this->statusSys = self::ER_OK;
            $this->writeMode = false;
        }

        private function resetStateOnly(): void
        {
            $this->entries = [];
            $this->numFiles = 0;
            $this->writeMode = false;
        }

        private function replaceEntry(string $name, string $contents): void
        {
            foreach ($this->entries as $index => $entry) {
                if ($entry['name'] === $name) {
                    $this->entries[$index] = [
                        'name' => $name,
                        'data' => $contents,
                        'mtime' => time(),
                    ];
                    $this->numFiles = count($this->entries);

                    return;
                }
            }

            $this->entries[] = [
                'name' => $name,
                'data' => $contents,
                'mtime' => time(),
            ];
            $this->numFiles = count($this->entries);
        }

        private function normalizeEntryName(string $name): string
        {
            $name = str_replace('\\', '/', trim($name));
            $name = preg_replace('/^\.\//', '', $name) ?? $name;

            return $name;
        }

        private function normalizeLookupName(string $name): string
        {
            $name = $this->normalizeEntryName($name);

            return ltrim($name, '/');
        }

        private function sliceEntryData(string $data, int $length): string
        {
            if ($length > 0) {
                return substr($data, 0, $length);
            }

            return $data;
        }

        private function buildStatRow(array $entry, int $index): array
        {
            return [
                'name' => $entry['name'],
                'index' => $index,
                'crc' => sprintf('%u', crc32($entry['data'])),
                'size' => strlen($entry['data']),
                'mtime' => $entry['mtime'],
                'comp_size' => strlen($entry['data']),
            ];
        }

        private function parseArchive(string $filename): bool
        {
            $binary = @file_get_contents($filename);
            if ($binary === false) {
                return false;
            }

            $eocdOffset = $this->findEndOfCentralDirectory($binary);
            if ($eocdOffset === null) {
                return false;
            }

            $eocd = unpack(
                'vdisk/vdiskStart/ventriesDisk/ventriesTotal/VcdSize/VcdOffset/vcommentLength',
                substr($binary, $eocdOffset + 4, 18)
            );
            if ($eocd === false) {
                return false;
            }

            $this->comment = (string) substr($binary, $eocdOffset + 22, (int) $eocd['commentLength']);

            $offset = (int) $eocd['cdOffset'];
            $totalEntries = (int) $eocd['entriesTotal'];

            for ($index = 0; $index < $totalEntries; $index++) {
                $header = substr($binary, $offset, 46);
                if (strlen($header) < 46) {
                    return false;
                }

                $signature = unpack('Vsignature', substr($header, 0, 4));
                if (! is_array($signature) || (int) $signature['signature'] !== 0x02014b50) {
                    return false;
                }

                $info = unpack(
                    'vversionMade/vversionNeeded/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vuncompressed/vnameLength/vextraLength/vcommentLength/vdisk/vinternalAttr/VexternalAttr/VlocalOffset',
                    substr($header, 4)
                );
                if ($info === false) {
                    return false;
                }

                $nameLength = (int) $info['nameLength'];
                $extraLength = (int) $info['extraLength'];
                $commentLength = (int) $info['commentLength'];
                $name = (string) substr($binary, $offset + 46, $nameLength);
                $localOffset = (int) $info['localOffset'];
                $localHeader = substr($binary, $localOffset, 30);
                if (strlen($localHeader) < 30) {
                    return false;
                }

                $localInfo = unpack('Vsignature/vversion/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vuncompressed/vnameLength/vextraLength', $localHeader);
                if ($localInfo === false) {
                    return false;
                }

                $dataOffset = $localOffset + 30 + (int) $localInfo['nameLength'] + (int) $localInfo['extraLength'];
                $compressedSize = (int) $info['compressed'];
                $compressedData = substr($binary, $dataOffset, $compressedSize);
                if ($compressedData === false) {
                    return false;
                }

                $data = match ((int) $info['method']) {
                    self::CM_STORE => $compressedData,
                    self::CM_DEFLATE => gzinflate($compressedData),
                    default => false,
                };
                if ($data === false) {
                    return false;
                }

                $this->entries[] = [
                    'name' => $name,
                    'data' => $data,
                    'mtime' => time(),
                ];

                $offset += 46 + $nameLength + $extraLength + $commentLength;
            }

            $this->numFiles = count($this->entries);

            return true;
        }

        private function findEndOfCentralDirectory(string $binary): ?int
        {
            $maxSearch = min(strlen($binary), 65557);
            $search = substr($binary, -$maxSearch);
            $position = strrpos($search, "\x50\x4b\x05\x06");

            if ($position === false) {
                return null;
            }

            return strlen($binary) - strlen($search) + $position;
        }

        private function buildArchiveBinary(): string
        {
            $localData = '';
            $centralDirectory = '';
            $offset = 0;
            $entryCount = count($this->entries);

            foreach ($this->entries as $entry) {
                $name = $entry['name'];
                $data = (string) $entry['data'];
                $timestamp = (int) ($entry['mtime'] ?? time());
                [$dosTime, $dosDate] = $this->toDosTimeDate($timestamp);

                $compressed = function_exists('gzdeflate') ? gzdeflate($data, 9) : false;
                $method = self::CM_STORE;
                if ($compressed !== false && strlen($compressed) < strlen($data)) {
                    $method = self::CM_DEFLATE;
                } else {
                    $compressed = $data;
                }

                $crc = (int) sprintf('%u', crc32($data));
                $compressedSize = strlen($compressed);
                $uncompressedSize = strlen($data);

                $localHeader = pack(
                    'VvvvvvVVVvv',
                    0x04034b50,
                    20,
                    0,
                    $method,
                    $dosTime,
                    $dosDate,
                    $crc,
                    $compressedSize,
                    $uncompressedSize,
                    strlen($name),
                    0
                ).$name.$compressed;

                $localData .= $localHeader;
                $centralDirectory .= pack(
                    'VvvvvvvVVVvvvvvVV',
                    0x02014b50,
                    20,
                    20,
                    0,
                    $method,
                    $dosTime,
                    $dosDate,
                    $crc,
                    $compressedSize,
                    $uncompressedSize,
                    strlen($name),
                    0,
                    0,
                    0,
                    0,
                    0,
                    $offset
                ).$name;

                $offset += strlen($localHeader);
            }

            $centralDirectorySize = strlen($centralDirectory);

            return $localData
                .$centralDirectory
                .pack(
                    'VvvvvVVv',
                    0x06054b50,
                    0,
                    0,
                    $entryCount,
                    $entryCount,
                    $centralDirectorySize,
                    strlen($localData),
                    0
                );
        }

        /**
         * @return array{0:int,1:int}
         */
        private function toDosTimeDate(int $timestamp): array
        {
            $timestamp = $timestamp > 0 ? $timestamp : time();
            $year = (int) gmdate('Y', $timestamp);
            if ($year < 1980) {
                $year = 1980;
            }

            $month = (int) gmdate('n', $timestamp);
            $day = (int) gmdate('j', $timestamp);
            $hour = (int) gmdate('G', $timestamp);
            $minute = (int) gmdate('i', $timestamp);
            $second = (int) gmdate('s', $timestamp);

            $dosDate = (($year - 1980) << 9) | ($month << 5) | $day;
            $dosTime = ($hour << 11) | ($minute << 5) | intdiv($second, 2);

            return [$dosTime, $dosDate];
        }
    }
}
