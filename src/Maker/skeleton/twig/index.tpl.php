{% extends '@GingermindsCore/crud/list.html.twig' %}

{# Column labels are translation keys, looked up in the resource domain then GingermindsCore. #}
{% set columns = [
    {label: '<?= $resource->snake ?>.field.name', property: 'name', sortable: true},
    {label: 'common.actions', align: 'end'},
] %}

{% block table_row %}
    <td>{{ item.name }}</td>
    <td class="text-end">{{ block('row_actions') }}</td>
{% endblock %}
