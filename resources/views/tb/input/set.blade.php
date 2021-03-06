
<select multiple="multiple" name="{{ $name }}[]" class="dblclick-edit-input form-control input-small unselectable">
    @foreach ($options as $value => $caption)
        @if (in_array($value, $selected))
            <option value="{{ $value }}" selected>{{ $caption }}</option>
        @else
            <option value="{{ $value }}">{{ $caption }}</option>
        @endif
    @endforeach
</select>
