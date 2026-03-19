<div class="block-title">{{ $title }}</div>
<table>
    <thead>
        <tr>
            <th>Account</th>
            <th class="num">Balance</th>
            @if(!empty($hasCompare))
                <th class="num">Previous</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @foreach(($tree ?? []) as $classNode)
            <tr>
                <td colspan="{{ !empty($hasCompare) ? 3 : 2 }}"><strong>{{ $classNode['class_name'] ?? '' }}</strong></td>
            </tr>

            @foreach(($classNode['groups'] ?? []) as $groupNode)
                <tr>
                    <td colspan="{{ !empty($hasCompare) ? 3 : 2 }}" style="padding-left: 16px;"><strong>{{ $groupNode['group_name'] ?? '' }}</strong></td>
                </tr>

                @foreach(($groupNode['accounts'] ?? []) as $acc)
                    <tr>
                        <td style="padding-left: 32px;">{{ $acc['account_code'] ?? '' }} - {{ $acc['account_name'] ?? '' }}</td>
                        <td class="num">{{ $fmt($acc['balance'] ?? 0) }}</td>
                        @if(!empty($hasCompare))
                            <td class="num">{{ ($acc['prev_balance'] ?? null) !== null ? $fmt($acc['prev_balance']) : '' }}</td>
                        @endif
                    </tr>
                @endforeach

                <tr>
                    <td style="padding-left: 16px;"><strong>Subtotal - {{ $groupNode['group_name'] ?? '' }}</strong></td>
                    <td class="num"><strong>{{ $fmt($groupNode['subtotal'] ?? 0) }}</strong></td>
                    @if(!empty($hasCompare))
                        <td class="num"><strong>{{ ($groupNode['prev_subtotal'] ?? null) !== null ? $fmt($groupNode['prev_subtotal']) : '' }}</strong></td>
                    @endif
                </tr>
            @endforeach

            <tr>
                <td><strong>Total - {{ $classNode['class_name'] ?? '' }}</strong></td>
                <td class="num"><strong>{{ $fmt($classNode['subtotal'] ?? 0) }}</strong></td>
                @if(!empty($hasCompare))
                    <td class="num"><strong>{{ ($classNode['prev_subtotal'] ?? null) !== null ? $fmt($classNode['prev_subtotal']) : '' }}</strong></td>
                @endif
            </tr>
        @endforeach

        @if(empty($tree ?? []))
            <tr>
                <td colspan="{{ !empty($hasCompare) ? 3 : 2 }}" style="text-align:center; color:#777; padding:10px;">No data</td>
            </tr>
        @endif
    </tbody>
</table>
