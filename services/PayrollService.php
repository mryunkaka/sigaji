<?php

final class PayrollService
{
    public static function ensureMasterGaji(int $userId): array
    {
        $master = fetch_one('SELECT * FROM master_gaji WHERE user_id = :user_id LIMIT 1', [
            'user_id' => $userId,
        ]);

        if ($master) {
            return $master;
        }

        execute_query(
            'INSERT INTO master_gaji (user_id, gaji_pokok, tunjangan_bbm, tunjangan_makan, tunjangan_jabatan, tunjangan_kehadiran, tunjangan_lainnya, potongan_terlambat, pot_bpjs_jht, pot_bpjs_kes, created_at, updated_at)
             VALUES (:user_id, 1500000, 0, 0, 0, 0, 0, 1000, 0, 0, :created_at, :updated_at)',
            [
                'user_id' => $userId,
                'created_at' => now_string(),
                'updated_at' => now_string(),
            ]
        );

        return fetch_one('SELECT * FROM master_gaji WHERE user_id = :user_id LIMIT 1', [
            'user_id' => $userId,
        ]) ?? [];
    }

    public static function calculateSalary(int $userId, string $tanggalAwal, string $tanggalAkhir): array
    {
        $master = self::ensureMasterGaji($userId);
        $hadir = (int) (fetch_one(
            "SELECT COUNT(*) AS total FROM absensi WHERE user_id = :user_id AND tanggal BETWEEN :start AND :end AND status = 'hadir'",
            ['user_id' => $userId, 'start' => $tanggalAwal, 'end' => $tanggalAkhir]
        )['total'] ?? 0);

        $alpa = (int) (fetch_one(
            "SELECT COUNT(*) AS total FROM absensi WHERE user_id = :user_id AND tanggal BETWEEN :start AND :end AND status = 'alpa'",
            ['user_id' => $userId, 'start' => $tanggalAwal, 'end' => $tanggalAkhir]
        )['total'] ?? 0);

        $totals = fetch_one(
            'SELECT
                COALESCE(SUM(total_menit_terlambat), 0) AS total_terlambat,
                COALESCE(SUM(lembur), 0) AS lembur,
                COALESCE(SUM(potongan_ijin), 0) AS potongan_ijin,
                COALESCE(SUM(potongan_khusus), 0) AS potongan_khusus
             FROM absensi
             WHERE user_id = :user_id AND tanggal BETWEEN :start AND :end',
            ['user_id' => $userId, 'start' => $tanggalAwal, 'end' => $tanggalAkhir]
        ) ?: [];

        $jumlahHari = (int) ((strtotime($tanggalAkhir) - strtotime($tanggalAwal)) / 86400) + 1;
        $jumlahHari = max(1, $jumlahHari);

        $gajiPokok = (float) ($master['gaji_pokok'] ?? 0);
        $tunjanganBbm = (float) ($master['tunjangan_bbm'] ?? 0);
        $tunjanganMakan = (float) ($master['tunjangan_makan'] ?? 0) * $hadir;
        $tunjanganJabatan = (float) ($master['tunjangan_jabatan'] ?? 0) * $hadir;
        $tunjanganKehadiran = (float) ($master['tunjangan_kehadiran'] ?? 0);
        $tunjanganLainnya = (float) ($master['tunjangan_lainnya'] ?? 0);
        $potonganTerlambatRate = (float) ($master['potongan_terlambat'] ?? 0);
        $potBpjsJht = (float) ($master['pot_bpjs_jht'] ?? 0);
        $potBpjsKes = (float) ($master['pot_bpjs_kes'] ?? 0);
        $totalTerlambat = (int) ($totals['total_terlambat'] ?? 0);
        $dendaTerlambat = $totalTerlambat * $potonganTerlambatRate;
        $potonganKehadiran = $alpa * ($gajiPokok / $jumlahHari);
        $potonganIjin = (float) ($totals['potongan_ijin'] ?? 0);
        $potonganKhusus = (float) ($totals['potongan_khusus'] ?? 0);
        $lembur = (float) ($totals['lembur'] ?? 0);

        $gajiKotor = $gajiPokok + $tunjanganBbm + $tunjanganMakan + $tunjanganJabatan + $tunjanganKehadiran + $tunjanganLainnya + $lembur;
        $totalPotongan = $potonganKehadiran + $potonganIjin + $potonganKhusus + $dendaTerlambat + $potBpjsJht + $potBpjsKes;
        $gajiBersih = $gajiKotor - $totalPotongan;

        return [
            'user_id' => $userId,
            'tanggal_awal_gaji' => $tanggalAwal,
            'tanggal_akhir_gaji' => $tanggalAkhir,
            'gaji_pokok' => (int) round($gajiPokok),
            'gaji_kotor' => (int) round($gajiKotor),
            'gaji_bersih' => (int) round($gajiBersih),
            'tunjangan_bbm' => (int) round($tunjanganBbm),
            'tunjangan_makan' => (int) round($tunjanganMakan),
            'tunjangan_jabatan' => (int) round($tunjanganJabatan),
            'tunjangan_kehadiran' => (int) round($tunjanganKehadiran),
            'tunjangan_lainnya' => (int) round($tunjanganLainnya),
            'lembur' => (int) round($lembur),
            'potongan_kehadiran' => (int) round($potonganKehadiran),
            'potongan_khusus' => (int) round($potonganKhusus),
            'potongan_ijin' => (int) round($potonganIjin),
            'potongan_terlambat' => (int) round($dendaTerlambat),
            'pot_bpjs_jht' => (int) round($potBpjsJht),
            'pot_bpjs_kes' => (int) round($potBpjsKes),
            'total_potongan' => (int) round($totalPotongan),
            'hadir' => $hadir,
            'alpa' => $alpa,
            'total_terlambat' => $totalTerlambat,
        ];
    }
}
