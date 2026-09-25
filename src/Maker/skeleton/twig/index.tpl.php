{% extends '@GingermindsCore/crud/list.html.twig' %}

{# Column labels are translation keys, looked up in the resource domain then GingermindsCore. #}
{% set columns = [
    {label: '<?= $resource->snake ?>.field.id', property: 'id', sortable: true},
    {label: 'common.actions', align: 'end'},
] %}

{% block table_row %}
    <td>{{ item.id }}</td>
    <td class="text-end">{{ block('row_actions') }}</td>
{% endblock %}
