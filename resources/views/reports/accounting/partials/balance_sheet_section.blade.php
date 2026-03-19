@php
    $hasCompare = !empty($compareAsOf);
@endphp

<div class="p-2 border-top">
    <strong>{{ $sectionTitle }}</strong>
</div>
<div class="table-responsive">
    <table class="table table-sm table-striped mb-0">
        <thead>
            <tr>
                <th>Account</th>
                <th class="text-right">Balance</th>
                @if($hasCompare)
                    <th class="text-right">Previous</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach(($sectionTree ?? []) as $classNode)
                <tr class="bg-light">
                    <td colspan="{{ $hasCompare ? 3 : 2 }}"><strong>{{ $classNode['class_name'] ?? '' }}</strong></td>
                </tr>

                @foreach(($classNode['groups'] ?? []) as $groupNode)
                    <tr>
                        <td colspan="{{ $hasCompare ? 3 : 2 }}" style="padding-left: 16px;"><strong>{{ $groupNode['group_name'] ?? '' }}</strong></td>
                    </tr>

                    @foreach(($groupNode['accounts'] ?? []) as $acc)
                        <tr>
                            <td style="padding-left: 32px;">
                                <a href="{{ route('reports.accounting.balance_sheet.account_lines', [
                                    'accountId' => $acc['account_id'] ?? 0,
                                    'as_of' => $asOf ?? null,
                                    'subshop_id' => $selectedSubshopId ?? null,
                                ]) }}" target="_blank">
                                    {{ $acc['account_code'] ?? '' }} - {{ $acc['account_name'] ?? '' }}
                                </a>
                            </td>
                            <td class="text-right">{{ $fmt($acc['balance'] ?? 0) }}</td>
                            @if($hasCompare)
                                <td class="text-right">{{ ($acc['prev_balance'] ?? null) !== null ? $fmt($acc['prev_balance']) : '' }}</td>
                            @endif
                        </tr>
                    @endforeach

                    <tr class="bg-white">
                        <td style="padding-left: 16px;"><strong>Subtotal - {{ $groupNode['group_name'] ?? '' }}</strong></td>
                        <td class="text-right"><strong>{{ $fmt($groupNode['subtotal'] ?? 0) }}</strong></td>
                        @if($hasCompare)
                            <td class="text-right"><strong>{{ ($groupNode['prev_subtotal'] ?? null) !== null ? $fmt($groupNode['prev_subtotal']) : '' }}</strong></td>
                        @endif
                    </tr>
                @endforeach

                <tr class="bg-white border-top">
                    <td><strong>Total - {{ $classNode['class_name'] ?? '' }}</strong></td>
                    <td class="text-right"><strong>{{ $fmt($classNode['subtotal'] ?? 0) }}</strong></td>
                    @if($hasCompare)
                        <td class="text-right"><strong>{{ ($classNode['prev_subtotal'] ?? null) !== null ? $fmt($classNode['prev_subtotal']) : '' }}</strong></td>
                    @endif
                </tr>
            @endforeach

            @if(empty($sectionTree ?? []))
                <tr>
                    <td colspan="{{ $hasCompare ? 3 : 2 }}" class="text-center text-muted p-3">No data</td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
