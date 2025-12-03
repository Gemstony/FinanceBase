<form method="POST" action="{{ route('subshops.choose.store') }}" class="form-inline ml-2">
  @csrf
  <input type="hidden" name="intended" value="{{ url()->current() }}">
  <select name="subshop_id" class="form-control form-control-sm" onchange="this.form.submit()">
    @php
      $user = auth()->user();
      $options = collect();
      if ($user) {
        // Accessible subshops: owner or assigned
        $owns = method_exists($user, 'hasShop') ? $user->hasShop() : false;
        if ($owns) {
          $shopIds = \App\Models\Shop::where('user_id', $user->id)->pluck('id');
          $options = \App\Models\SubShop::whereIn('shop_id', $shopIds)->active()->orderBy('name')->get(['id','name']);
        } elseif (method_exists($user, 'subshops')) {
          $options = $user->subshops()->wherePivot('is_active',1)->active()->orderBy('name')->get(['sub_shops.id','sub_shops.name']);
        }
      }
    @endphp
    @foreach($options as $opt)
      <option value="{{ $opt->id }}" @selected(session('subshop_id') == $opt->id)>{{ $opt->name }}</option>
    @endforeach
  </select>
</form>
